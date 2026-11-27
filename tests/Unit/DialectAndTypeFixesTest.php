<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Unit;

use KaririCode\Database\Driver\PdoMySqlDriver;
use KaririCode\Database\Driver\PdoPgSqlDriver;
use KaririCode\Database\DsnParser;
use KaririCode\Database\Enum\TransactionIsolationLevel;
use KaririCode\Database\Exception\ConfigurationException;
use KaririCode\Database\Platform\MySqlPlatform;
use KaririCode\Database\Platform\PostgreSqlPlatform;
use KaririCode\Database\Platform\SqlitePlatform;
use KaririCode\Database\Type\DateIntervalType;
use KaririCode\Database\Type\DateTimeTzType;
use KaririCode\Database\ValueObject\ConnectionParams;
use KaririCode\Database\ValueObject\PlatformVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * SQL and conversions that only a real server proved wrong, pinned here
 * so they hold where no server runs.
 */
#[CoversClass(MySqlPlatform::class)]
#[CoversClass(PostgreSqlPlatform::class)]
#[CoversClass(SqlitePlatform::class)]
#[CoversClass(DateIntervalType::class)]
#[CoversClass(DateTimeTzType::class)]
#[CoversClass(DsnParser::class)]
#[CoversClass(ConnectionParams::class)]
#[CoversClass(PdoMySqlDriver::class)]
#[CoversClass(PdoPgSqlDriver::class)]
final class DialectAndTypeFixesTest extends TestCase
{
    public function testAnUpsertWithOnlyKeyColumnsKeepsTheExistingRow(): void
    {
        self::assertSame(
            'INSERT INTO `tags` (`name`) VALUES (?) ON DUPLICATE KEY UPDATE `name` = `name`',
            self::mySql()->getUpsertSql('tags', ['name'], ['name']),
        );
        self::assertSame(
            'INSERT INTO "tags" ("name") VALUES (?) ON CONFLICT ("name") DO NOTHING',
            (new PostgreSqlPlatform(new PlatformVersion('postgresql', 16)))->getUpsertSql('tags', ['name'], ['name']),
        );
        self::assertSame(
            'INSERT INTO "tags" ("name") VALUES (?) ON CONFLICT ("name") DO NOTHING',
            (new SqlitePlatform(new PlatformVersion('sqlite', 3, 45)))->getUpsertSql('tags', ['name'], ['name']),
        );
        self::assertSame(
            'INSERT INTO "tags" ("name", "n") VALUES (?, ?) ON CONFLICT ("name") DO UPDATE SET "n" = excluded."n"',
            (new SqlitePlatform(new PlatformVersion('sqlite', 3, 45)))->getUpsertSql('tags', ['name', 'n'], ['name']),
        );
    }

    /** Without SESSION the level lasted one transaction while transactionIsolation() kept reporting it. */
    public function testMySqlIsolationAppliesToTheSession(): void
    {
        self::assertSame('SET SESSION TRANSACTION ISOLATION LEVEL SERIALIZABLE', self::mySql()->setTransactionIsolationSql(TransactionIsolationLevel::Serializable));
    }

    /** MariaDB answers NULL to GET_LOCK with a negative timeout. */
    public function testTheBlockingLockWaitsWithAPositiveTimeout(): void
    {
        self::assertSame("SELECT GET_LOCK('42', 31536000)", self::mySql()->getAdvisoryLockSql(42));
        self::assertSame("SELECT GET_LOCK('42', 0)", self::mySql()->getTryAdvisoryLockSql(42));
    }

    public function testANegativeIntervalKeepsItsSign(): void
    {
        $type = new DateIntervalType();
        $interval = new \DateInterval('P1DT2H');
        $interval->invert = 1;

        $stored = $type->convertToDatabaseValue($interval, self::mySql());
        self::assertSame('-P0Y0M1DT2H0M0S', $stored);
        $read = $type->convertToPhpValue($stored, self::mySql());
        self::assertNotNull($read);
        self::assertSame([1, 1, 2], [$read->invert, $read->d, $read->h]);
        self::assertSame(0, $type->convertToPhpValue('P1D', self::mySql())?->invert);
        self::assertSame('VARCHAR(255)', $type->getSqlDeclaration([], new PostgreSqlPlatform(new PlatformVersion('postgresql', 16))));
    }

    public function testMySqlStoresTheInstantInUtc(): void
    {
        $type = new DateTimeTzType();
        $mariaDb = new MySqlPlatform(new PlatformVersion('mariadb', 10, 11));

        self::assertSame('2026-11-27 08:00:00', $type->convertToDatabaseValue(new \DateTimeImmutable('2026-11-27 10:00:00+02:00'), self::mySql()));
        self::assertSame('2026-11-27 08:00:00', $type->convertToDatabaseValue(new \DateTimeImmutable('2026-11-27 10:00:00+02:00'), $mariaDb));
        self::assertSame('2026-11-27T08:00:00+00:00', $type->convertToPhpValue('2026-11-27 08:00:00', self::mySql())?->format(\DATE_ATOM));
        self::assertSame(
            '2026-11-27 10:00:00+02:00',
            $type->convertToDatabaseValue(new \DateTimeImmutable('2026-11-27 10:00:00+02:00'), new SqlitePlatform(new PlatformVersion('sqlite', 3, 45))),
        );
    }

    /** @return iterable<string, array{string, string}> */
    public static function sqlitePaths(): iterable
    {
        yield 'absolute' => ['sqlite:////var/lib/app/app.db', '/var/lib/app/app.db'];
        yield 'relative' => ['sqlite:///data/app.db', 'data/app.db'];
        yield 'memory' => ['sqlite:///:memory:', ':memory:'];
        yield 'no slash' => ['sqlite:app.db', 'app.db'];
        yield 'server database' => ['mysql://u@db/shop', 'shop'];
    }

    #[DataProvider('sqlitePaths')]
    public function testOnlyTheFirstSlashSeparatesThePath(string $dsn, string $database): void
    {
        self::assertSame($database, (new DsnParser())->parse($dsn)->database);
    }

    public function testAnUnknownDriverIsAConfigurationError(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionCode(6020);

        (new DsnParser(['oracle' => 'pdo_oci']))->parse('oracle://u@db/x')->driverType();
    }

    /** The charset is part of the DSN (MySQL) or of SET NAMES (PostgreSQL): it is checked before connecting. */
    public function testACharsetThatCouldAlterTheDsnIsRejectedBeforeConnecting(): void
    {
        foreach ([new PdoMySqlDriver(), new PdoPgSqlDriver()] as $driver) {
            try {
                $driver->connect(new ConnectionParams(driver: 'pdo_mysql', host: '192.0.2.1', charset: 'utf8;host=evil'));
                self::fail('the charset was accepted');
            } catch (ConfigurationException $e) {
                self::assertSame(6019, $e->getCode());
            }
        }
    }

    private static function mySql(): MySqlPlatform
    {
        return new MySqlPlatform(new PlatformVersion('mysql', 8, 4));
    }
}
