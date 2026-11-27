<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Unit;

use KaririCode\Database\Driver\ExceptionConverter\MySqlExceptionConverter;
use KaririCode\Database\DsnParser;
use KaririCode\Database\Exception\ConfigurationException;
use KaririCode\Database\Exception\ConnectionException;
use KaririCode\Database\Exception\QueryException;
use KaririCode\Database\Middleware\RetryMiddleware;
use KaririCode\Database\Platform\MySqlPlatform;
use KaririCode\Database\Platform\PostgreSqlPlatform;
use KaririCode\Database\Resilience\TransientFailureRetryPolicy;
use KaririCode\Database\Tests\Support\FlakyDriver;
use KaririCode\Database\Tests\Support\Sqlite;
use KaririCode\Database\ValueObject\ConnectionParams;
use PHPUnit\Framework\TestCase;

final class FailureHandlingTest extends TestCase
{
    public function testDsnErrorsNeverEchoTheDsn(): void
    {
        foreach (['mysql://app:s3cr3t-pw@db:99999/shop' => 6016, 'redisx://app:s3cr3t-pw@db/shop' => 6017] as $dsn => $code) { // secret-scan:allow fixture // secret-scan: allow (credencial falsa: prova que a senha nao vaza)
            try {
                (new DsnParser())->parse($dsn);
                self::fail('esperava excecao');
            } catch (ConfigurationException $e) {
                self::assertSame($code, $e->getCode());
                self::assertStringNotContainsString('s3cr3t', $e->getMessage());
            }
        }
    }

    public function testMySqlUnknownDatabaseIsConfigurationWithoutSql(): void
    {
        $e = (new MySqlExceptionConverter())->convert($this->pdo('HY000', 1049, "Unknown database 'shop'"), 'SELECT secret FROM t');
        self::assertInstanceOf(ConfigurationException::class, $e);
        self::assertSame(6013, $e->getCode());
        self::assertStringNotContainsString('SELECT', $e->getMessage());
    }

    public function testMySqlDuplicateIsUniqueViolationWithoutTheValue(): void
    {
        $e = (new MySqlExceptionConverter())->convert($this->pdo('23000', 1062, "Duplicate entry 'joao@x.com' for key 'users.email'"));
        self::assertInstanceOf(QueryException::class, $e);
        self::assertSame(6032, $e->getCode());
        self::assertSame('23000', $e->sqlState);
        self::assertStringNotContainsString('joao@x.com', $e->getMessage());
    }

    public function testUnknownDriverErrorIsExecuteFailedNotSyntaxError(): void
    {
        self::assertSame(6039, (new MySqlExceptionConverter())->convert(new \PDOException('boom'))->getCode());
    }

    public function testRetryRecoversFromTransientConnectionFailures(): void
    {
        $driver = new FlakyDriver(2, ConnectionException::connectionFailed());
        $retry = new RetryMiddleware(new TransientFailureRetryPolicy(maxAttempts: 3, baseDelayMilliseconds: 1, maxDelayMilliseconds: 2));
        $retry->wrap($driver)->connect(new ConnectionParams(driver: 'pdo_sqlite', database: ':memory:'));
        self::assertSame(3, $driver->calls);
    }

    public function testRetryNeverRepeatsConfigurationErrors(): void
    {
        $driver = new FlakyDriver(5, ConfigurationException::authenticationFailed());
        try {
            (new RetryMiddleware())->wrap($driver)->connect(new ConnectionParams(driver: 'pdo_sqlite', database: ':memory:'));
            self::fail('esperava excecao');
        } catch (ConfigurationException) {
            self::assertSame(1, $driver->calls);
        }
    }

    public function testRetryGivesUpAfterMaxAttempts(): void
    {
        $driver = new FlakyDriver(9, ConnectionException::connectionLost());
        try {
            (new RetryMiddleware(new TransientFailureRetryPolicy(3, 1, 1)))->wrap($driver)->connect(new ConnectionParams(driver: 'pdo_sqlite', database: ':memory:'));
            self::fail('esperava excecao');
        } catch (ConnectionException) {
            self::assertSame(3, $driver->calls);
        }
    }

    public function testFullJitterStaysUnderTheCeiling(): void
    {
        $policy = new TransientFailureRetryPolicy(5, 100, 400);
        foreach (range(1, 60) as $i) {
            $attempt = 1 + $i % 4;
            $delay = $policy->delayMilliseconds(ConnectionException::connectionLost(), $attempt);
            self::assertGreaterThanOrEqual(0, $delay);
            self::assertLessThanOrEqual(min(400, 100 * 2 ** ($attempt - 1)), $delay);
        }
    }

    public function testAdvisoryLockReleaseSqlComesFromThePlatformContract(): void
    {
        $version = Sqlite::connection()->platform->version;
        self::assertStringContainsString('RELEASE_LOCK', (string) (new MySqlPlatform($version))->getReleaseAdvisoryLockSql(42));
        self::assertNull((new PostgreSqlPlatform($version))->getReleaseAdvisoryLockSql(42));
        self::assertNull(Sqlite::connection()->platform->getReleaseAdvisoryLockSql(42));
    }

    /** Licao 10 do handoff do ORM: a senha nao aparece em stack traces. */
    public function testPasswordParameterIsSensitive(): void
    {
        $param = new \ReflectionParameter([ConnectionParams::class, '__construct'], 'password');
        self::assertCount(1, $param->getAttributes(\SensitiveParameter::class));
    }

    private function pdo(string $sqlState, int $vendor, string $text): \PDOException
    {
        $e = new \PDOException("SQLSTATE[{$sqlState}]: [{$vendor}] {$text}");
        (new \ReflectionProperty(\Exception::class, 'code'))->setValue($e, $sqlState);
        $e->errorInfo = [$sqlState, $vendor, $text];

        return $e;
    }

}
