<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Integration\Sqlite;

use KaririCode\Database\Connection\ConnectionFactory;
use KaririCode\Database\Driver\ExceptionConverter\AbstractExceptionConverter;
use KaririCode\Database\Driver\ExceptionConverter\SqliteExceptionConverter;
use KaririCode\Database\Driver\Pdo\PdoConnection;
use KaririCode\Database\Driver\Pdo\PdoResult;
use KaririCode\Database\Driver\Pdo\PdoStatement;
use KaririCode\Database\Driver\PdoSqliteDriver;
use KaririCode\Database\Exception\ConnectionException;
use KaririCode\Database\Exception\DatabaseException;
use KaririCode\Database\Exception\QueryException;
use KaririCode\Database\Tests\Support\Sqlite;
use KaririCode\Database\ValueObject\ConnectionParams;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * ADR-004: no layer above the driver sees a raw PDOException. Each error
 * path of the PDO wrappers (prepare, exec, execute, fetch, transactions,
 * connect) arrives as a package exception with the driver error kept as
 * its cause.
 */
#[CoversClass(PdoConnection::class)]
#[CoversClass(PdoStatement::class)]
#[CoversClass(PdoResult::class)]
#[CoversClass(PdoSqliteDriver::class)]
#[CoversClass(SqliteExceptionConverter::class)]
#[CoversClass(AbstractExceptionConverter::class)]
final class ErrorConversionTest extends TestCase
{
    public function testStatementErrorsArriveAsPackageExceptions(): void
    {
        $connection = Sqlite::connection();
        $connection->executeStatement('CREATE TABLE parent (id INTEGER PRIMARY KEY, code TEXT NOT NULL UNIQUE)');
        $connection->executeStatement('CREATE TABLE child (id INTEGER PRIMARY KEY, parent_id INTEGER NOT NULL REFERENCES parent (id))');
        $connection->insert('parent', ['id' => 1, 'code' => 'a']);

        self::assertConverted(6039, static fn(): mixed => $connection->executeQuery('SELEC 1'));
        self::assertConverted(6039, static fn(): int => $connection->executeStatement('DELETE FROM missing_table'));
        self::assertConverted(6032, static fn(): int => $connection->insert('parent', ['id' => 2, 'code' => 'a']));
        self::assertConverted(6032, static fn(): int => $connection->executeStatement("INSERT INTO parent (id, code) VALUES (3, 'a')"));
        self::assertConverted(6034, static fn(): int => $connection->executeStatement('INSERT INTO parent (id, code) VALUES (4, NULL)'));
        self::assertConverted(6033, static fn(): int => $connection->insert('child', ['parent_id' => 99]));
    }

    /** SQLite evaluates rows lazily: the second row fails on the second fetch, after execute() succeeded. */
    public function testAnErrorWhileFetchingIsConverted(): void
    {
        $connection = Sqlite::connection();
        $connection->executeStatement('CREATE TABLE docs (v TEXT)');
        $connection->executeStatement("INSERT INTO docs VALUES ('{\"a\":1}'), ('not json')");
        $sql = "SELECT json_extract(v, '$.a') AS a FROM docs ORDER BY rowid";

        self::assertConverted(6039, static fn(): array => $connection->executeQuery($sql)->fetchAllAssociative());
        try {
            $connection->executeQuery($sql)->fetchAllAssociative();
        } catch (QueryException $e) {
            self::assertSame('SQLSTATE[HY000]: malformed JSON', $e->getPrevious()?->getMessage(), 'the cause carries the driver text from errorInfo()');
        }
        self::assertConverted(6039, static fn(): array => $connection->executeQuery($sql)->fetchAllNumeric());
        self::assertConverted(6039, static fn(): array => $connection->executeQuery($sql)->fetchFirstColumn());
        self::assertConverted(6039, static fn(): array => iterator_to_array($connection->executeQuery($sql)->iterateAssociative()));
        self::assertConverted(6039, static fn(): array => iterator_to_array($connection->executeQuery($sql)->iterateNumeric()));

        $result = $connection->executeQuery($sql);
        self::assertSame(1, $result->fetchOne());
        self::assertConverted(6039, static fn(): mixed => $result->fetchOne());
    }

    public function testABusyDatabaseIsALockTimeout(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'kcdb');
        self::assertIsString($file);

        try {
            $writer = (new PdoSqliteDriver())->connect(new ConnectionParams(driver: 'pdo_sqlite', database: $file));
            $writer->exec('CREATE TABLE t (v INTEGER)');
            $writer->exec('BEGIN IMMEDIATE');
            $writer->exec('INSERT INTO t VALUES (1)');
            $blocked = (new PdoSqliteDriver())->connect(new ConnectionParams(driver: 'pdo_sqlite', database: $file, driverOptions: [\PDO::ATTR_TIMEOUT => 0]));

            self::assertConverted(6036, static fn(): int => $blocked->exec('INSERT INTO t VALUES (2)'));
            $writer->exec('ROLLBACK');
        } finally {
            foreach ([$file, "{$file}-wal", "{$file}-shm"] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    public function testTransactionCallsOnTheDriverAreConverted(): void
    {
        $driverConnection = (new PdoSqliteDriver())->connect(new ConnectionParams(driver: 'pdo_sqlite'));

        self::assertConverted(6039, static fn() => $driverConnection->commit());
        self::assertConverted(6039, static fn() => $driverConnection->rollBack());
        $driverConnection->beginTransaction();
        self::assertConverted(6039, static fn() => $driverConnection->beginTransaction());
        $driverConnection->rollBack();
    }

    public function testAFileThatCannotBeOpenedIsAConnectionFailure(): void
    {
        try {
            (new ConnectionFactory())->createConnection(new ConnectionParams(driver: 'pdo_sqlite', database: '/kc-missing-directory/app.db'));
            self::fail('the database opened');
        } catch (ConnectionException $e) {
            self::assertSame(6010, $e->getCode());
            self::assertInstanceOf(\PDOException::class, $e->getPrevious());
        }
    }

    public function testPdoErrorModeSilentStillFails(): void
    {
        $driverConnection = (new PdoSqliteDriver())->connect(new ConnectionParams(driver: 'pdo_sqlite', driverOptions: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT]));

        self::assertConverted(6038, static fn() => $driverConnection->prepare('SELEC 1'));
        self::assertConverted(6039, static fn() => $driverConnection->exec('SELEC 1'));
    }

    private static function assertConverted(int $code, \Closure $operation): void
    {
        try {
            $operation();
            self::fail("expected the database error {$code}");
        } catch (DatabaseException $e) {
            self::assertSame($code, $e->getCode());
            if ($code !== 6038 && !($code === 6039 && $e->getPrevious() === null)) {
                self::assertInstanceOf(\PDOException::class, $e->getPrevious());
            }
            self::assertInstanceOf($code === 6010 ? ConnectionException::class : QueryException::class, $e);
        }
    }
}
