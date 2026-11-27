<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Integration\Server;

use KaririCode\Database\Connection\ConnectionFactory;
use KaririCode\Database\Connection\DefaultConnection;
use KaririCode\Database\DsnParser;
use KaririCode\Database\Enum\ReferentialAction;
use KaririCode\Database\Enum\TransactionIsolationLevel;
use KaririCode\Database\Exception\ConfigurationException;
use KaririCode\Database\Exception\DatabaseException;
use KaririCode\Database\Exception\QueryException;
use KaririCode\Database\Contract\SchemaInspector;
use KaririCode\Database\Tests\Support\TypeRoundTrip;
use KaririCode\Database\Type\ScalarValue;
use KaririCode\Database\ValueObject\ConnectionParams;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The behaviour a SQLite file cannot show: credentials and unknown
 * databases refused by a server, vendor error codes, session isolation,
 * advisory locks between two sessions, the information_schema readers and
 * every built-in type in a server column. A subclass names its server
 * through an environment variable holding a DSN; without it the tests are
 * skipped.
 */
abstract class ServerTestCase extends TestCase
{
    use TypeRoundTrip;

    protected DefaultConnection $connection;

    /** Environment variable that holds the DSN, e.g. pgsql://user:pass@127.0.0.1:5432/db. */
    abstract protected static function urlVariable(): string;

    abstract protected function autoIncrementKey(): string;

    /** Code for a database the server does not have (MySQL hides it behind 1044 for a user without global grants). */
    abstract protected function unknownDatabaseCode(): int;

    /** Isolation level of the session as the server reports it. */
    abstract protected function sessionIsolation(): string;

    abstract protected function sequenceFor(string $table): ?string;

    abstract protected function inspector(): SchemaInspector;

    /** The update action a catalog reports for a key declared without one. */
    abstract protected function defaultUpdateAction(): ReferentialAction;

    /** An explicit schema argument is read instead of the current one. */
    abstract protected function assertExplicitSchemaIsRead(SchemaInspector $inspector): void;

    protected function setUp(): void
    {
        $this->connection = self::connect(self::params());
        $this->dropTables();
    }

    protected function tearDown(): void
    {
        // Sem servidor o setUp() pula o teste antes de abrir a conexao.
        if (isset($this->connection)) {
            $this->dropTables();
        }
    }

    public function testWrongCredentialsAreAConfigurationError(): void
    {
        $this->expectRefusal(self::params(password: 'wrong-' . bin2hex(random_bytes(4))), 6012); // secret-scan:allow: a wrong password on purpose
    }

    public function testAnUnknownDatabaseIsAConfigurationError(): void
    {
        $this->expectRefusal(self::params(database: 'kc_missing_' . bin2hex(random_bytes(3))), $this->unknownDatabaseCode());
    }

    public function testIntegrityViolationsKeepTheirMeaning(): void
    {
        $this->createTables();
        $this->connection->insert('kc_parent', ['code' => 'a']);

        self::assertQueryError(6032, fn(): int => $this->connection->insert('kc_parent', ['code' => 'a']));
        self::assertQueryError(6032, fn(): int => $this->connection->executeStatement("INSERT INTO kc_parent (code) VALUES ('a')"));
        self::assertQueryError(6034, fn(): int => $this->connection->executeStatement('INSERT INTO kc_parent (code) VALUES (NULL)'));
        self::assertQueryError(6033, fn(): int => $this->connection->insert('kc_child', ['parent_id' => 999]));
    }

    public function testSyntaxErrorsAndUnknownTablesAreQueryErrors(): void
    {
        self::assertQueryError(6030, fn(): mixed => $this->connection->executeQuery('SELEC 1')->fetchOne());
        self::assertQueryError(6030, fn(): mixed => $this->connection->executeQuery('SELECT * FROM kc_missing_table')->fetchOne());
    }

    public function testBuilderInsertsBindTheirValues(): void
    {
        $this->createTables();

        self::assertSame(1, $this->connection->createQueryBuilder()->insert('kc_parent')->values(['code' => 'one', 'note' => 'n'])->executeStatement());
        self::assertSame('1', $this->connection->lastInsertId($this->sequenceFor('kc_parent')));
        self::assertSame(2, $this->connection->createQueryBuilder()->insert('kc_parent')->bulkValues([['code' => 'two'], ['code' => 'three', 'note' => 'x']])->executeStatement());

        self::assertSame(
            [['code' => 'one', 'note' => 'n'], ['code' => 'three', 'note' => 'x'], ['code' => 'two', 'note' => null]],
            $this->connection->executeQuery('SELECT code, note FROM kc_parent ORDER BY code')->fetchAllAssociative(),
        );
    }

    public function testANestedRollbackUndoesOnlyItsSavepoint(): void
    {
        $this->createTables();

        $this->connection->beginTransaction();
        $this->connection->insert('kc_parent', ['code' => 'outer']);
        $this->connection->beginTransaction();
        $this->connection->insert('kc_parent', ['code' => 'inner']);
        $this->connection->rollBack();
        $this->connection->commit();

        self::assertSame(['outer'], $this->connection->executeQuery('SELECT code FROM kc_parent')->fetchFirstColumn());
    }

    public function testTheIsolationLevelHoldsForTheSession(): void
    {
        $this->connection->setTransactionIsolation(TransactionIsolationLevel::Serializable);
        $this->connection->transactional(static fn(): bool => true);

        self::assertSame('SERIALIZABLE', strtoupper(str_replace(['-', '_'], ' ', $this->sessionIsolation())));
        self::assertSame(TransactionIsolationLevel::Serializable, $this->connection->transactionIsolation());
    }

    public function testAnAdvisoryLockExcludesOtherSessions(): void
    {
        $other = self::connect(self::params());

        $this->connection->beginTransaction();
        $this->connection->advisoryLock(4242);
        self::assertFalse($other->tryAdvisoryLock(4242), 'held by the first session');
        self::assertTrue($this->connection->releaseAdvisoryLock(4242));
        $this->connection->commit();

        $other->beginTransaction();
        self::assertTrue($other->tryAdvisoryLock(4242), 'free again');
        $other->releaseAdvisoryLock(4242);
        $other->commit();
    }

    public function testUpsertUpdatesTheRowOrKeepsItWhenOnlyKeysAreGiven(): void
    {
        $this->createTables();
        $platform = $this->connection->platform;
        $this->connection->insert('kc_parent', ['code' => 'a', 'note' => 'first']);

        $this->connection->executeStatement($platform->getUpsertSql('kc_parent', ['code', 'note'], ['code']), ['a', 'second']);
        $this->connection->executeStatement($platform->getUpsertSql('kc_parent', ['code'], ['code']), ['a']);
        $this->connection->executeStatement($platform->getUpsertSql('kc_parent', ['code'], ['code']), ['b']);

        self::assertSame(
            [['code' => 'a', 'note' => 'second'], ['code' => 'b', 'note' => null]],
            $this->connection->executeQuery('SELECT code, note FROM kc_parent ORDER BY code')->fetchAllAssociative(),
        );
    }

    public function testLimitBooleanAndTimestampSql(): void
    {
        $this->createTables();
        foreach (['a', 'b', 'c', 'd'] as $code) {
            $this->connection->insert('kc_parent', ['code' => $code]);
        }
        $platform = $this->connection->platform;

        self::assertSame(['b', 'c'], $this->connection->executeQuery($platform->getLimitSql('SELECT code FROM kc_parent ORDER BY code', 2, 1))->fetchFirstColumn());
        self::assertSame(1, $this->connection->executeQuery('SELECT COUNT(*) FROM kc_parent WHERE ' . $platform->getBooleanLiteral(true) . " AND code = 'a'")->fetchOne());
        self::assertNotFalse(strtotime(ScalarValue::asString($this->connection->executeQuery('SELECT ' . $platform->currentTimestampSql())->fetchOne(), 'string')));
    }

    public function testDriverOptionsReplaceTheDefaultsAndPreparesAreNative(): void
    {
        $base = self::params();
        $numeric = self::connect(new ConnectionParams(
            driver: $base->driver,
            host: $base->host,
            port: $base->port,
            database: $base->database,
            username: $base->username,
            password: $base->password,
            charset: $base->charset,
            driverOptions: [\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_NUM],
        ));
        $native = $numeric->nativeConnection();

        self::assertInstanceOf(\PDO::class, $native);
        self::assertSame(\PDO::FETCH_NUM, $native->getAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE));
        self::assertSame(\PDO::ERRMODE_EXCEPTION, $native->getAttribute(\PDO::ATTR_ERRMODE));
        self::assertFalse($native->getAttribute(\PDO::ATTR_EMULATE_PREPARES));
    }

    /** exec('SELECT 1') left a result pending on MySQL and broke the next query (error 2014). */
    public function testPingLeavesTheSessionUsable(): void
    {
        self::assertTrue($this->connection->ping());
        self::assertSame(2, $this->connection->executeQuery('SELECT 2')->fetchOne());
    }

    public function testTheInspectorReadsTablesColumnsIndexesAndForeignKeys(): void
    {
        $this->createTables();
        $this->connection->executeStatement('CREATE INDEX kc_child_parent ON kc_child (parent_id)');
        $this->connection->executeStatement('CREATE TABLE kc_pair (a INT NOT NULL, b INT NOT NULL, PRIMARY KEY (a, b))');
        $this->connection->executeStatement(
            'CREATE TABLE kc_pair_ref (id INT NOT NULL PRIMARY KEY, x INT NOT NULL, y INT NOT NULL, parent_id INT NULL, '
            . 'CONSTRAINT kc_pair_ref_pair FOREIGN KEY (x, y) REFERENCES kc_pair (a, b) ON UPDATE CASCADE ON DELETE RESTRICT, '
            . 'CONSTRAINT kc_pair_ref_parent FOREIGN KEY (parent_id) REFERENCES kc_parent (id) ON DELETE SET NULL)',
        );
        $inspector = $this->inspector();

        self::assertSame(['kc_child', 'kc_pair', 'kc_pair_ref', 'kc_parent'], array_values(array_filter($inspector->listTableNames(), static fn(string $name): bool => str_starts_with($name, 'kc_'))));
        self::assertTrue($inspector->tableExists('kc_parent'));
        self::assertFalse($inspector->tableExists('kc_missing_table'));

        $columns = [];
        foreach ($inspector->listTableColumns('kc_parent') as $column) {
            $columns[$column->name] = [$column->nullable, $column->autoIncrement, $column->default];
        }
        self::assertSame([false, true], array_slice($columns['id'], 0, 2));
        self::assertSame([false, false, null], $columns['code']);
        self::assertSame([true, false, null], $columns['note'], 'a nullable column without default has no default');
        self::assertSame([false, false, 'open'], $columns['status'], 'a literal default comes back unquoted');
        self::assertSame([true, false, "it's"], $columns['motto'], 'a quote inside the literal is unescaped');

        $indexes = [];
        foreach ($inspector->listTableIndexes('kc_child') as $index) {
            $indexes[$index->primary ? 'primary' : $index->name] = [$index->columns, $index->unique];
        }
        self::assertSame([['id'], true], $indexes['primary']);
        self::assertSame([['parent_id'], false], $indexes['kc_child_parent']);
        $pairIndexes = $inspector->listTableIndexes('kc_pair');
        self::assertCount(1, $pairIndexes);
        self::assertSame([['a', 'b'], true, true], [$pairIndexes[0]->columns, $pairIndexes[0]->unique, $pairIndexes[0]->primary]);

        $keys = [];
        foreach ($inspector->listTableForeignKeys('kc_pair_ref') as $key) {
            $keys[$key->name] = [$key->localColumns, $key->foreignTable, $key->foreignColumns, $key->onUpdate, $key->onDelete];
        }
        ksort($keys);
        self::assertSame([
            'kc_pair_ref_pair' => [['x', 'y'], 'kc_pair', ['a', 'b'], ReferentialAction::Cascade, ReferentialAction::Restrict],
            'kc_pair_ref_parent' => [['parent_id'], 'kc_parent', ['id'], $this->defaultUpdateAction(), ReferentialAction::SetNull],
        ], $keys);

        $this->assertExplicitSchemaIsRead($inspector);
    }

    #[DataProvider('builtInTypeSamples')]
    public function testBuiltInTypeRoundTrips(string $type, mixed $value): void
    {
        self::assertTypeRoundTrips($this->connection, $this->connection->platform, $type, $value);
    }

    protected function createTables(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE kc_parent (' . $this->autoIncrementKey() . ", code VARCHAR(20) NOT NULL UNIQUE, note VARCHAR(50) NULL, status VARCHAR(10) NOT NULL DEFAULT 'open', motto VARCHAR(20) NULL DEFAULT 'it''s')",
        );
        $this->connection->executeStatement(
            'CREATE TABLE kc_child (' . $this->autoIncrementKey() . ', parent_id INT NOT NULL, CONSTRAINT kc_child_fk FOREIGN KEY (parent_id) REFERENCES kc_parent (id) ON DELETE CASCADE)',
        );
    }

    protected static function params(?string $password = null, ?string $database = null): ConnectionParams
    {
        $url = getenv(static::urlVariable());
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('set ' . static::urlVariable() . ' to run these tests against a server');
        }
        $params = (new DsnParser())->parse($url);

        return new ConnectionParams(
            driver: $params->driver,
            host: $params->host,
            port: $params->port,
            database: $database ?? $params->database,
            username: $params->username,
            password: $password ?? $params->password,
            charset: $params->charset,
        );
    }

    protected static function connect(ConnectionParams $params): DefaultConnection
    {
        return (new ConnectionFactory())->createConnection($params);
    }

    private function dropTables(): void
    {
        foreach (['kc_pair_ref', 'kc_pair', 'kc_child', 'kc_parent', 'kc_types'] as $table) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS ' . $table);
        }
    }

    private function expectRefusal(ConnectionParams $params, int $code): void
    {
        try {
            self::connect($params);
            self::fail('the server accepted the connection');
        } catch (ConfigurationException $e) {
            self::assertSame($code, $e->getCode());
            self::assertInstanceOf(\PDOException::class, $e->getPrevious());
        }
    }

    private static function assertQueryError(int $code, \Closure $statement): void
    {
        try {
            $statement();
            self::fail("expected the database error {$code}");
        } catch (DatabaseException $e) {
            self::assertInstanceOf(QueryException::class, $e);
            self::assertSame($code, $e->getCode());
            self::assertInstanceOf(\PDOException::class, $e->getPrevious());
        }
    }
}
