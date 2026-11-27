<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Unit;

use KaririCode\Database\Driver\ExceptionConverter\AbstractExceptionConverter;
use KaririCode\Database\Driver\ExceptionConverter\MySqlExceptionConverter;
use KaririCode\Database\Driver\ExceptionConverter\PostgreSqlExceptionConverter;
use KaririCode\Database\Driver\ExceptionConverter\SqliteExceptionConverter;
use KaririCode\Database\Exception\ConfigurationException;
use KaririCode\Database\Exception\ConnectionException;
use KaririCode\Database\Exception\DatabaseException;
use KaririCode\Database\Exception\QueryException;
use KaririCode\Database\Resilience\TransientFailureRetryPolicy;
use KaririCode\Database\ValueObject\PlatformVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins the vendor-code and SQLSTATE tables of the converters, the
 * transient classification and backoff of the retry policy, and version
 * comparison: each row is one mapping a mutant could silently break.
 */
#[CoversClass(MySqlExceptionConverter::class)]
#[CoversClass(PostgreSqlExceptionConverter::class)]
#[CoversClass(SqliteExceptionConverter::class)]
#[CoversClass(AbstractExceptionConverter::class)]
#[CoversClass(TransientFailureRetryPolicy::class)]
#[CoversClass(PlatformVersion::class)]
final class ConversionAndRetryTest extends TestCase
{
    /** @return iterable<string, array{int, class-string<DatabaseException>, int}> */
    public static function mySqlVendorCodes(): iterable
    {
        yield 'access denied' => [1045, ConfigurationException::class, 6012];
        yield 'unknown database' => [1049, ConfigurationException::class, 6013];
        yield 'column cannot be null' => [1048, QueryException::class, 6034];
        yield 'duplicate entry' => [1062, QueryException::class, 6032];
        yield 'lock wait timeout' => [1205, QueryException::class, 6036];
        yield 'deadlock' => [1213, QueryException::class, 6035];
        yield 'parent row referenced' => [1451, QueryException::class, 6033];
        yield 'child row without parent' => [1452, QueryException::class, 6033];
        yield 'cannot connect' => [2002, ConnectionException::class, 6010];
        yield 'server has gone away' => [2006, ConnectionException::class, 6011];
        yield 'too many connections' => [1040, ConnectionException::class, 6015];
        yield 'no access to the database' => [1044, ConfigurationException::class, 6012];
        yield 'lost during the query' => [2013, ConnectionException::class, 6011];
    }

    /** @return iterable<string, array{string, int}> */
    public static function postgreSqlConnectMessages(): iterable
    {
        yield 'wrong password' => ['FATAL:  password authentication failed for user "app"', 6012];
        yield 'unknown database' => ['FATAL:  database "shop" does not exist', 6013];
        yield 'server full' => ['FATAL:  sorry, too many clients already', 6015];
        yield 'translated message' => ['FATAL:  autenticação do tipo senha falhou para o usuário "app"', 6010];
        yield 'server down' => ['could not connect to server: Connection refused', 6010];
    }

    /** libpq reports every refused connection as 08006; only the message tells them apart. */
    #[DataProvider('postgreSqlConnectMessages')]
    public function testPostgreSqlConnectFailuresAreReadFromTheMessage(string $message, int $code): void
    {
        $exception = self::pdo('08006', 7, "connection to server failed: {$message}");

        self::assertSame($code, (new PostgreSqlExceptionConverter())->convertConnectFailure($exception)->getCode());
        self::assertSame(6011, (new PostgreSqlExceptionConverter())->convert($exception)->getCode(), 'after connecting, 08006 is a lost connection');
    }

    public function testAtConnectTimeOnlyConfigurationAndConnectionOutcomesRemain(): void
    {
        $converter = new MySqlExceptionConverter();

        self::assertSame(6012, $converter->convertConnectFailure(self::pdo('HY000', 1045))->getCode());
        self::assertSame(6015, $converter->convertConnectFailure(self::pdo('HY000', 1040))->getCode());
        self::assertSame(6010, $converter->convertConnectFailure(self::pdo('HY000', 2006))->getCode(), 'a lost connection is a failed one');
        self::assertSame(6010, $converter->convertConnectFailure(self::pdo('HY000', 1062))->getCode(), 'no query outcome at connect time');
        self::assertSame(6010, $converter->convertConnectFailure(new \RuntimeException('boom'))->getCode());
    }

    /** @return iterable<string, array{int, string, int}> */
    public static function sqliteErrors(): iterable
    {
        yield 'busy' => [5, 'database is locked', 6036];
        yield 'locked' => [6, 'database table is locked', 6036];
        yield 'unique' => [19, 'UNIQUE constraint failed: users.email', 6032];
        yield 'foreign key' => [19, 'FOREIGN KEY constraint failed', 6033];
        yield 'not null' => [19, 'NOT NULL constraint failed: users.name', 6034];
        yield 'syntax' => [1, 'near "SELEC": syntax error', 6039];
    }

    #[DataProvider('sqliteErrors')]
    public function testSqliteResultCodesAndConstraintTexts(int $resultCode, string $message, int $code): void
    {
        self::assertSame($code, (new SqliteExceptionConverter())->convert(self::pdo('HY000', $resultCode, $message))->getCode());
    }

    /**
     * The SQLSTATE is HY000, so only the vendor table can produce the code.
     *
     * @param class-string<DatabaseException> $class
     */
    #[DataProvider('mySqlVendorCodes')]
    public function testMySqlVendorCodeDecidesTheException(int $vendor, string $class, int $code): void
    {
        $converted = (new MySqlExceptionConverter())->convert(self::pdo('HY000', $vendor));

        self::assertInstanceOf($class, $converted);
        self::assertSame($code, $converted->getCode());
    }

    /** @return iterable<string, array{string, int}> */
    public static function sqlStates(): iterable
    {
        yield 'connection exception' => ['08001', 6010];
        yield 'invalid authorization' => ['28000', 6012];
        yield 'unique violation' => ['23505', 6032];
        yield 'foreign key violation' => ['23503', 6033];
        yield 'not null violation' => ['23502', 6034];
        yield 'other integrity violation' => ['23000', 6031];
        yield 'serialization failure' => ['40001', 6035];
        yield 'syntax error or access rule' => ['42000', 6030];
        yield 'unclassified' => ['HY000', 6039];
    }

    /** A vendor code the table does not know falls back to the SQLSTATE class. */
    #[DataProvider('sqlStates')]
    public function testSqlStateClassDecidesWhenTheVendorCodeIsUnknown(string $sqlState, int $code): void
    {
        self::assertSame($code, (new MySqlExceptionConverter())->convert(self::pdo($sqlState, 9999))->getCode());
    }

    public function testANonPdoErrorIsAnExecuteFailure(): void
    {
        $converted = (new MySqlExceptionConverter())->convert(new \RuntimeException('boom', 1062));

        self::assertInstanceOf(QueryException::class, $converted);
        self::assertSame(6039, $converted->getCode());
    }

    /** Drivers may refine the SQLSTATE step; the base class must call the override. */
    public function testASubclassCanRefineTheSqlStateStep(): void
    {
        $converter = new class extends AbstractExceptionConverter {
            #[\Override]
            protected function convertFromVendorCode(\Throwable $exception, ?string $sql): ?DatabaseException
            {
                return null;
            }

            #[\Override]
            protected function convertFromSqlState(string $sqlState, \Throwable $previous, ?string $sql): DatabaseException
            {
                return QueryException::invalidParameterCount($previous);
            }

            #[\Override]
            protected function convertIntegrityViolation(string $sqlState, \Throwable $previous): DatabaseException
            {
                return QueryException::prepareFailed($previous);
            }
        };

        self::assertSame(6037, $converter->convert(self::pdo('08001', 0))->getCode());
    }

    /** @return iterable<string, array{\Throwable, bool}> */
    public static function errors(): iterable
    {
        yield 'connection failed' => [ConnectionException::connectionFailed(), true];
        yield 'connection lost' => [ConnectionException::connectionLost(), true];
        yield 'deadlock' => [QueryException::deadlock(), true];
        yield 'lock timeout' => [QueryException::lockTimeout(), true];
        yield 'not null violation' => [QueryException::notNullViolation(), false];
        yield 'invalid parameter count' => [QueryException::invalidParameterCount(), false];
        yield 'syntax error' => [QueryException::syntaxError(), false];
        yield 'credentials rejected' => [ConfigurationException::authenticationFailed(), false];
        yield 'foreign error with a deadlock code' => [new \RuntimeException('not ours', 6035), false];
    }

    #[DataProvider('errors')]
    public function testOnlyConnectionFailuresDeadlocksAndLockTimeoutsAreRetried(\Throwable $error, bool $transient): void
    {
        self::assertSame($transient, (new TransientFailureRetryPolicy())->shouldRetry($error, 1));
    }

    public function testDefaultPolicyAllowsThreeAttempts(): void
    {
        $policy = new TransientFailureRetryPolicy();
        $error = ConnectionException::connectionLost();

        self::assertTrue($policy->shouldRetry($error, 2));
        self::assertFalse($policy->shouldRetry($error, 3));
    }

    /** @return iterable<string, array{int, int}> */
    public static function defaultCeilings(): iterable
    {
        yield 'first retry' => [1, 100];
        yield 'second retry' => [2, 200];
        yield 'fifth retry' => [5, 1600];
        yield 'capped' => [6, 2000];
    }

    /** Full jitter: delays spread over the whole [0, ceiling] range and never pass it. */
    #[DataProvider('defaultCeilings')]
    public function testDefaultBackoffDoublesFromOneHundredUpToTwoSeconds(int $attempt, int $ceiling): void
    {
        $delays = self::delays(new TransientFailureRetryPolicy(), $attempt, 300);

        self::assertLessThanOrEqual($ceiling, max($delays));
        self::assertGreaterThan(intdiv($ceiling * 3, 4), max($delays));
        self::assertLessThan(intdiv($ceiling, 4), min($delays));
    }

    public function testAttemptZeroUsesTheBaseDelay(): void
    {
        $delays = self::delays(new TransientFailureRetryPolicy(baseDelayMilliseconds: 40, maxDelayMilliseconds: 1000), 0, 200);

        self::assertLessThanOrEqual(40, max($delays));
        self::assertGreaterThan(30, max($delays));
    }

    public function testTheExponentStopsAtTwenty(): void
    {
        $ceiling = 1 << 20;
        $delays = self::delays(new TransientFailureRetryPolicy(baseDelayMilliseconds: 1, maxDelayMilliseconds: PHP_INT_MAX), 30, 200);

        self::assertLessThanOrEqual($ceiling, max($delays));
        self::assertGreaterThan(intdiv($ceiling * 3, 4), max($delays));
    }

    public function testDelaysIncludeZeroAndNeverGoBelowIt(): void
    {
        $tiny = self::delays(new TransientFailureRetryPolicy(baseDelayMilliseconds: 1, maxDelayMilliseconds: 1), 1, 200);
        $none = self::delays(new TransientFailureRetryPolicy(baseDelayMilliseconds: 0), 3, 100);
        $negative = self::delays(new TransientFailureRetryPolicy(baseDelayMilliseconds: -5), 1, 20);

        self::assertContains(0, $tiny);
        self::assertContains(1, $tiny);
        self::assertSame([0], array_values(array_unique($none)));
        self::assertSame([0], array_values(array_unique($negative)));
    }

    public function testVersionDefaultsToMinorAndPatchZero(): void
    {
        $version = new PlatformVersion('mysql', 8);

        self::assertSame([0, 0], [$version->minor, $version->patch]);
        self::assertSame('mysql 8.0.0', (string) $version);
    }

    /** @return iterable<string, array{int, int, int, int, bool}> */
    public static function comparisons(): iterable
    {
        yield 'same major, minor omitted' => [8, 0, 8, 0, true];
        yield 'same major, higher minor asked' => [8, 0, 8, 1, false];
        yield 'same version' => [8, 4, 8, 4, true];
        yield 'one minor short' => [8, 3, 8, 4, false];
        yield 'newer major beats any minor' => [9, 0, 8, 5, true];
        yield 'older major loses' => [7, 9, 8, 0, false];
        yield 'older major with a higher minor' => [7, 9, 8, 5, false];
    }

    #[DataProvider('comparisons')]
    public function testVersionComparesMajorThenMinor(int $major, int $minor, int $wantMajor, int $wantMinor, bool $expected): void
    {
        $version = new PlatformVersion('postgresql', $major, $minor);

        self::assertSame($expected, $wantMinor === 0 ? $version->gte($wantMajor) : $version->gte($wantMajor, $wantMinor));
    }

    private static function pdo(string $sqlState, int $vendor, string $text = 'fixture'): \PDOException
    {
        $exception = new \PDOException("SQLSTATE[{$sqlState}]: [{$vendor}] {$text}");
        (new \ReflectionProperty(\Exception::class, 'code'))->setValue($exception, $sqlState);
        $exception->errorInfo = [$sqlState, $vendor, $text];

        return $exception;
    }

    /**
     * @param positive-int $samples
     * @return non-empty-list<int>
     */
    private static function delays(TransientFailureRetryPolicy $policy, int $attempt, int $samples): array
    {
        return array_map(
            static fn(int $i): int => $policy->delayMilliseconds(ConnectionException::connectionLost(), $attempt),
            range(1, $samples),
        );
    }
}
