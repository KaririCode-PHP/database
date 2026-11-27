<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Integration\Server;

use KaririCode\Database\Connection\ConnectionFactory;
use KaririCode\Database\Contract\SchemaInspector;
use KaririCode\Database\Enum\ReferentialAction;
use KaririCode\Database\Exception\QueryException;
use KaririCode\Database\Driver\PdoMySqlDriver;
use KaririCode\Database\Middleware\TenancyMiddleware;
use KaririCode\Database\Platform\MySqlPlatform;
use KaririCode\Database\Schema\MySqlSchemaInspector;
use KaririCode\Database\Type\ScalarValue;
use KaririCode\Database\ValueObject\ColumnMetadata;
use KaririCode\Database\ValueObject\ConnectionParams;

/** Runs when KARIRICODE_DATABASE_MYSQL_URL holds a DSN such as mysql://user:pass@127.0.0.1:3306/db (MySQL or MariaDB). */
final class MySqlServerTest extends ServerTestCase
{
    public function testConnectsAndDetectsTheVendor(): void
    {
        self::assertInstanceOf(MySqlPlatform::class, $this->connection->platform);
        $version = $this->connection->platform->version;
        self::assertTrue($version->isMySQL() || $version->isMariaDb());
        self::assertSame('utf8mb4', $this->connection->executeQuery('SELECT @@character_set_client')->fetchOne());
    }

    /** The test user may not create databases, so the switch targets the configured one. */
    public function testTenancyRunsUseOnConnect(): void
    {
        $params = self::params();
        $tenant = (new ConnectionFactory([new TenancyMiddleware(static fn(): string => $params->database)]))->createConnection($params);

        self::assertSame($params->database, $tenant->executeQuery('SELECT DATABASE()')->fetchOne());
    }

    public function testColumnCommentsAndTheNativeHandle(): void
    {
        $this->connection->executeStatement("CREATE TABLE kc_types (v INT NULL COMMENT 'reading in kelvin', w INT NULL)");

        $comments = [];
        foreach ($this->inspector()->listTableColumns('kc_types') as $column) {
            $comments[$column->name] = $column->comment;
        }

        self::assertSame(['v' => 'reading in kelvin', 'w' => null], $comments);
        self::assertInstanceOf(\Pdo\Mysql::class, $this->connection->nativeConnection());
    }

    /** Without a selected database DATABASE() is NULL: the inspector cannot guess one. */
    public function testTheInspectorNeedsADatabase(): void
    {
        $base = self::params();
        $connection = self::connect(new ConnectionParams(driver: $base->driver, host: $base->host, port: $base->port, database: '', username: $base->username, password: $base->password, charset: $base->charset));

        $this->expectException(QueryException::class);
        $this->expectExceptionCode(6102);
        (new MySqlSchemaInspector($connection))->listTableNames();
    }

    #[\Override]
    protected function defaultUpdateAction(): ReferentialAction
    {
        return $this->connection->platform->version->isMariaDb() ? ReferentialAction::Restrict : ReferentialAction::NoAction;
    }

    #[\Override]
    protected function assertExplicitSchemaIsRead(SchemaInspector $inspector): void
    {
        self::assertTrue($inspector->tableExists('TABLES', 'information_schema'));
        self::assertFalse($inspector->tableExists('TABLES'));
        self::assertSame([], $inspector->listTableNames('information_schema'), 'information_schema holds views, not base tables');
        self::assertContains('TABLE_NAME', array_map(static fn(ColumnMetadata $c): string => $c->name, $inspector->listTableColumns('TABLES', 'information_schema')));
    }

    #[\Override]
    protected static function urlVariable(): string
    {
        return 'KARIRICODE_DATABASE_MYSQL_URL';
    }

    #[\Override]
    protected function autoIncrementKey(): string
    {
        return 'id INT AUTO_INCREMENT PRIMARY KEY';
    }

    #[\Override]
    protected function unknownDatabaseCode(): int
    {
        return 6012;
    }

    #[\Override]
    protected function sessionIsolation(): string
    {
        $variable = $this->connection->platform->version->isMariaDb() ? '@@SESSION.tx_isolation' : '@@SESSION.transaction_isolation';

        return ScalarValue::asString($this->connection->executeQuery("SELECT {$variable}")->fetchOne(), 'string');
    }

    #[\Override]
    protected function sequenceFor(string $table): ?string
    {
        return null;
    }

    #[\Override]
    protected function inspector(): SchemaInspector
    {
        return new MySqlSchemaInspector($this->connection);
    }
}
