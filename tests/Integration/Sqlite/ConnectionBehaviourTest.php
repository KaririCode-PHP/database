<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Integration\Sqlite;

use KaririCode\Database\Cache\CacheProfile;
use KaririCode\Database\Connection\ConnectionFactory;
use KaririCode\Database\Connection\DefaultConnection;
use KaririCode\Database\Connection\ReadWriteConnection;
use KaririCode\Database\Contract\Driver;
use KaririCode\Database\Contract\DriverConnection;
use KaririCode\Database\Contract\Middleware;
use KaririCode\Database\Driver\Pdo\PdoConnection;
use KaririCode\Database\Driver\Pdo\PdoStatement;
use KaririCode\Database\Driver\PdoSqliteDriver;
use KaririCode\Database\Enum\ParameterType;
use KaririCode\Database\Event\QueryExecuted;
use KaririCode\Database\Event\TransactionRolledBack;
use KaririCode\Database\Exception\ConnectionException;
use KaririCode\Database\Exception\PlatformException;
use KaririCode\Database\Exception\TransactionException;
use KaririCode\Database\Middleware\LoggingMiddleware;
use KaririCode\Database\Platform\SqlitePlatform;
use KaririCode\Database\Tests\Support\ArrayCache;
use KaririCode\Database\Tests\Support\RecordingDispatcher;
use KaririCode\Database\Tests\Support\RecordingLogger;
use KaririCode\Database\Tests\Support\Sqlite;
use KaririCode\Database\Type\ScalarValue;
use KaririCode\Database\ValueObject\ConnectionParams;
use KaririCode\Database\ValueObject\PlatformVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins what DefaultConnection, its drivers and middleware do around a
 * statement: timings and events, parameter types, result caching,
 * transaction nesting with savepoints, middleware order and driver options.
 */
#[CoversClass(DefaultConnection::class)]
#[CoversClass(ConnectionFactory::class)]
#[CoversClass(LoggingMiddleware::class)]
#[CoversClass(PdoStatement::class)]
#[CoversClass(PdoConnection::class)]
#[CoversClass(PdoSqliteDriver::class)]
#[CoversClass(ReadWriteConnection::class)]
final class ConnectionBehaviourTest extends TestCase
{
    private const float MAX_PLAUSIBLE_MS = 10_000.0;

    public function testQueriesAndStatementsReportAPlausibleDuration(): void
    {
        $events = new RecordingDispatcher();
        $connection = Sqlite::connection(dispatcher: $events);

        $connection->executeStatement('CREATE TABLE t (id INTEGER)');
        $connection->executeStatement('INSERT INTO t (id) VALUES (?), (?)', [1, 2]);
        $connection->executeQuery('SELECT id FROM t');

        $executed = array_values(array_filter($events->events, static fn(object $e): bool => $e instanceof QueryExecuted));
        self::assertCount(3, $executed);
        self::assertSame(2, $executed[1]->rowCount, 'the statement event carries the affected rows');
        foreach ($executed as $event) {
            self::assertGreaterThanOrEqual(0.0, $event->executionTimeMs);
            self::assertLessThan(self::MAX_PLAUSIBLE_MS, $event->executionTimeMs);
        }
    }

    public function testDeclaredParameterTypesReachTheDriver(): void
    {
        $connection = Sqlite::connection();

        self::assertSame('integer', $connection->executeQuery('SELECT typeof(?)', [5], [ParameterType::INTEGER->value])->fetchOne());
        self::assertSame('text', $connection->executeQuery('SELECT typeof(?)', [5])->fetchOne());
    }

    public function testTheLoggerSeesConnectionDetailsButNotValues(): void
    {
        $logger = new RecordingLogger();
        $connection = Sqlite::connection([new LoggingMiddleware($logger)]);
        $connection->executeQuery('SELECT ? + ?', [1, 2]);

        $connected = self::contexts($logger, 'Database connection established.');
        self::assertCount(1, $connected);
        $context = $connected[0];
        self::assertSame(['driver', 'host', 'port', 'database', 'duration_ms'], array_keys($context));
        self::assertSame(['pdo_sqlite', 'localhost', 3306, ':memory:'], [$context['driver'], $context['host'], $context['port'], $context['database']]);
        self::assertIsFloat($context['duration_ms']);
        self::assertGreaterThanOrEqual(0.0, $context['duration_ms']);
        self::assertLessThan(self::MAX_PLAUSIBLE_MS, $context['duration_ms']);

        $statement = self::lastStatementLog($logger, 'SELECT ? + ?');
        self::assertSame(2, $statement['parameter_count']);
        self::assertGreaterThanOrEqual(0.0, $statement['duration_ms']);
        self::assertLessThan(self::MAX_PLAUSIBLE_MS, $statement['duration_ms']);
    }

    public function testTheLoggedStatementCountsParametersPassedToExecute(): void
    {
        $logger = new RecordingLogger();
        $driver = (new LoggingMiddleware($logger))->wrap(new PdoSqliteDriver());
        $connection = $driver->connect(new ConnectionParams(driver: 'pdo_sqlite', database: ':memory:'));

        $connection->prepare('SELECT ?, ?, ?')->execute([1, 2, 3]);

        self::assertSame(3, self::lastStatementLog($logger, 'SELECT ?, ?, ?')['parameter_count']);
    }

    public function testBindingWithoutATypeBindsAString(): void
    {
        $logged = (new LoggingMiddleware(new RecordingLogger()))->wrap(new PdoSqliteDriver())
            ->connect(new ConnectionParams(driver: 'pdo_sqlite', database: ':memory:'))
            ->prepare('SELECT typeof(?)');
        $logged->bindValue(1, 5);

        $plain = (new PdoSqliteDriver())->connect(new ConnectionParams(driver: 'pdo_sqlite', database: ':memory:'))->prepare('SELECT typeof(?)');
        $plain->bindValue(1, 5);

        self::assertSame('text', $logged->execute()->fetchOne());
        self::assertSame('text', $plain->execute()->fetchOne());
    }

    /** @return iterable<string, array{mixed, int, string}> */
    public static function parameterTypes(): iterable
    {
        yield 'integer' => [5, ParameterType::INTEGER->value, 'integer'];
        yield 'string' => [5, ParameterType::STRING->value, 'text'];
        yield 'binary' => ['bytes', ParameterType::BINARY->value, 'blob'];
        yield 'boolean' => [true, ParameterType::BOOLEAN->value, 'integer'];
        yield 'null' => [null, ParameterType::NULL->value, 'null'];
        yield 'ascii falls back to string' => [5, ParameterType::ASCII->value, 'text'];
    }

    #[DataProvider('parameterTypes')]
    public function testEachParameterTypeMapsToItsPdoType(mixed $value, int $type, string $sqliteType): void
    {
        $statement = (new PdoSqliteDriver())->connect(new ConnectionParams(driver: 'pdo_sqlite', database: ':memory:'))->prepare('SELECT typeof(?)');
        $statement->bindValue(1, $value, $type);

        self::assertSame($sqliteType, $statement->execute()->fetchOne());
    }

    public function testDriverOptionsOverrideTheDefaultsAndForeignKeysAreOn(): void
    {
        $connection = (new PdoSqliteDriver())->connect(new ConnectionParams(
            driver: 'pdo_sqlite',
            database: ':memory:',
            driverOptions: [\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_NUM],
        ));
        $native = $connection->nativeConnection();

        self::assertInstanceOf(\Pdo\Sqlite::class, $native);
        self::assertSame(\PDO::FETCH_NUM, $native->getAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE));
        self::assertSame(1, ScalarValue::asInt(self::pragma($native, 'foreign_keys'), 'integer'));
    }

    public function testAFileDatabaseUsesWriteAheadLogging(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'kcdb');
        self::assertIsString($file);

        try {
            $native = (new PdoSqliteDriver())->connect(new ConnectionParams(driver: 'pdo_sqlite', database: $file))->nativeConnection();
            self::assertInstanceOf(\PDO::class, $native);
            self::assertSame('wal', self::pragma($native, 'journal_mode'));
        } finally {
            foreach ([$file, "{$file}-wal", "{$file}-shm"] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    public function testTheProfileCacheWinsOverTheDefaultCache(): void
    {
        $default = new ArrayCache();
        $own = new ArrayCache();
        $connection = self::connectionWithCache($default);

        $connection->executeCacheQuery('SELECT 1 AS one', [], [], new CacheProfile(cache: $own));

        self::assertCount(1, $own->items);
        self::assertSame([], $default->items);
    }

    public function testATtlOfZeroCachesWithoutExpiry(): void
    {
        $cache = new ArrayCache();
        $connection = self::connectionWithCache($cache);

        $connection->executeCacheQuery('SELECT 1', [], [], new CacheProfile(ttl: 0));
        self::assertNull($cache->lastTtl);

        $connection->executeCacheQuery('SELECT 2', [], [], new CacheProfile(ttl: 30));
        self::assertSame(30, $cache->lastTtl);
    }

    public function testACachedResultIsServedWithoutQuerying(): void
    {
        $events = new RecordingDispatcher();
        $cache = new ArrayCache();
        $connection = self::connectionWithCache($cache, $events);
        $profile = new CacheProfile(key: 'rows');

        $first = $connection->executeCacheQuery('SELECT 7 AS n', [], [], $profile)->fetchAllAssociative();
        $second = $connection->executeCacheQuery('SELECT 7 AS n', [], [], $profile)->fetchAllAssociative();

        self::assertSame([['n' => 7]], $first);
        self::assertSame($first, $second);
        self::assertCount(1, array_filter($events->events, static fn(object $e): bool => $e instanceof QueryExecuted));
    }

    public function testATamperedCacheEntryCountsAsAMiss(): void
    {
        $cache = new ArrayCache();
        $cache->items['rows'] = [['n' => 1], 'not a row'];
        $connection = self::connectionWithCache($cache);

        $rows = $connection->executeCacheQuery('SELECT 7 AS n', [], [], new CacheProfile(key: 'rows'))->fetchAllAssociative();

        self::assertSame([['n' => 7]], $rows);
    }

    public function testCommitAndRollBackNeedAnActiveTransaction(): void
    {
        $connection = Sqlite::connection();

        foreach (['commit', 'rollBack'] as $method) {
            try {
                $connection->{$method}();
                self::fail("{$method}() without a transaction must fail");
            } catch (TransactionException $e) {
                self::assertSame('noActiveTransaction', self::factoryOf($e));
            }
        }
    }

    public function testCommitEndsTheDriverTransaction(): void
    {
        $connection = Sqlite::connection();

        $connection->beginTransaction();
        $connection->commit();
        $connection->beginTransaction();
        $connection->commit();

        self::assertFalse($connection->isTransactionActive());
        $native = $connection->nativeConnection();
        self::assertInstanceOf(\PDO::class, $native);
        self::assertFalse($native->inTransaction());
    }

    public function testNestedTransactionsUseNumberedSavepoints(): void
    {
        $logger = new RecordingLogger();
        $events = new RecordingDispatcher();
        $connection = Sqlite::connection([new LoggingMiddleware($logger)], $events);

        $connection->beginTransaction();
        $connection->beginTransaction();
        $connection->beginTransaction();
        $connection->commit();
        $connection->rollBack();
        $connection->commit();

        $sql = array_values(array_filter(self::loggedSql($logger), static fn(string $s): bool => str_contains($s, 'SAVEPOINT')));
        self::assertSame([
            'SAVEPOINT kariricode_sp_2',
            'SAVEPOINT kariricode_sp_3',
            'RELEASE SAVEPOINT kariricode_sp_3',
            'ROLLBACK TO SAVEPOINT kariricode_sp_2',
        ], $sql);

        $rollbacks = array_values(array_filter($events->events, static fn(object $e): bool => $e instanceof TransactionRolledBack));
        self::assertCount(1, $rollbacks);
        self::assertSame(1, $rollbacks[0]->nestingLevel);
        self::assertTrue($rollbacks[0]->toSavepoint);
    }

    public function testOnlyANestedBeginNeedsSavepointSupport(): void
    {
        $driver = (new PdoSqliteDriver())->connect(new ConnectionParams(driver: 'pdo_sqlite', database: ':memory:'));
        $connection = new DefaultConnection($driver, new SqlitePlatform(new PlatformVersion('sqlite', 3, 5)));

        $connection->beginTransaction();
        self::assertSame(1, $connection->transactionNestingLevel());

        $this->expectException(PlatformException::class);
        $connection->beginTransaction();
    }

    public function testTransactionalCommitsBeforeReturning(): void
    {
        $connection = Sqlite::connection();
        $connection->executeStatement('CREATE TABLE t (id INTEGER)');

        $result = $connection->transactional(static fn(DefaultConnection $c): int => $c->insert('t', ['id' => 1]));

        self::assertSame(1, $result);
        self::assertFalse($connection->isTransactionActive());
    }

    public function testMiddlewareRunsInTheOrderGiven(): void
    {
        $calls = new \ArrayObject();
        $recording = static fn(string $label): Middleware => new class ($label, $calls) implements Middleware {
            /** @param \ArrayObject<int, string> $calls */
            public function __construct(private readonly string $label, private readonly \ArrayObject $calls) {}

            #[\Override]
            public function wrap(Driver $driver): Driver
            {
                return new class ($driver, $this->label, $this->calls) implements Driver {
                    /** @param \ArrayObject<int, string> $calls */
                    public function __construct(private readonly Driver $inner, private readonly string $label, private readonly \ArrayObject $calls) {}

                    #[\Override]
                    public function connect(ConnectionParams $params): DriverConnection
                    {
                        $this->calls->append($this->label);

                        return $this->inner->connect($params);
                    }
                };
            }
        };

        Sqlite::connection([$recording('first'), $recording('second')]);

        self::assertSame(['first', 'second'], $calls->getArrayCopy());
    }

    public function testThePlatformVersionIsTheServerVersion(): void
    {
        $connection = Sqlite::connection();
        $reported = $connection->executeQuery('SELECT sqlite_version()')->fetchOne();
        self::assertIsString($reported);
        [$major, $minor, $patch] = array_map(intval(...), explode('.', $reported));

        $version = $connection->platform->version;
        self::assertSame([$major, $minor, $patch], [$version->major, $version->minor, $version->patch]);
    }

    public function testAMySqlServerThatRefusesTheConnectionIsAConnectionFailure(): void
    {
        try {
            (new ConnectionFactory())->createConnection(new ConnectionParams(driver: 'pdo_mysql', host: '127.0.0.1', port: 1, database: 'shop'));
            self::fail('expected the connection to fail');
        } catch (ConnectionException $e) {
            self::assertSame(6010, $e->getCode());
        }
    }

    public function testReadsStickToThePrimaryAfterAWriteByDefault(): void
    {
        $primary = Sqlite::connection();
        $replica = Sqlite::connection();
        foreach ([$primary, $replica] as $i => $connection) {
            $connection->executeStatement('CREATE TABLE who (name TEXT)');
            $connection->insert('who', ['name' => $i === 0 ? 'primary' : 'replica']);
        }
        $routed = new ReadWriteConnection($primary, [$replica]);

        self::assertSame('replica', $routed->executeQuery('SELECT name FROM who')->fetchOne());
        $routed->executeStatement('UPDATE who SET name = name');
        self::assertSame('primary', $routed->executeQuery('SELECT name FROM who')->fetchOne());
    }

    private static function connectionWithCache(ArrayCache $cache, ?RecordingDispatcher $events = null): DefaultConnection
    {
        $base = Sqlite::connection();
        $driver = (new PdoSqliteDriver())->connect(new ConnectionParams(driver: 'pdo_sqlite', database: ':memory:'));

        return new DefaultConnection($driver, $base->platform, $events, $cache);
    }

    /** @return array<string, mixed> */
    private static function lastStatementLog(RecordingLogger $logger, string $sql): array
    {
        $found = null;
        foreach (self::contexts($logger, 'Statement executed.') as $context) {
            if (($context['sql'] ?? null) === $sql) {
                $found = $context;
            }
        }
        self::assertNotNull($found, "no log record for {$sql}");

        return $found;
    }

    /** @return list<string> */
    private static function loggedSql(RecordingLogger $logger): array
    {
        $sql = [];
        foreach (self::contexts($logger, 'Statement executed.') as $context) {
            if (is_string($context['sql'] ?? null)) {
                $sql[] = $context['sql'];
            }
        }

        return $sql;
    }

    /** @return list<array<string, mixed>> */
    private static function contexts(RecordingLogger $logger, string $message): array
    {
        $contexts = [];
        foreach ($logger->records as [, $recorded, $data]) {
            if ($recorded !== $message) {
                continue;
            }
            $context = [];
            foreach ($data as $key => $value) {
                if (is_string($key)) {
                    $context[$key] = $value;
                }
            }
            $contexts[] = $context;
        }

        return $contexts;
    }

    private static function pragma(\PDO $pdo, string $name): mixed
    {
        $statement = $pdo->query("PRAGMA {$name}");
        self::assertInstanceOf(\PDOStatement::class, $statement);

        return $statement->fetchColumn();
    }

    private static function factoryOf(TransactionException $e): string
    {
        return $e->getCode() === TransactionException::noActiveTransaction()->getCode() ? 'noActiveTransaction' : 'other';
    }
}
