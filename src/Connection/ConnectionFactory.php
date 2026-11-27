<?php

declare(strict_types=1);

namespace KaririCode\Database\Connection;

use KaririCode\Database\Contract\Driver;
use KaririCode\Database\Contract\DriverConnection;
use KaririCode\Database\Contract\Middleware;
use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Driver\PdoMySqlDriver;
use KaririCode\Database\Driver\PdoPgSqlDriver;
use KaririCode\Database\Driver\PdoSqliteDriver;
use KaririCode\Database\Enum\DriverType;
use KaririCode\Database\Event\ConnectionError;
use KaririCode\Database\Event\ConnectionEstablished;
use KaririCode\Database\Exception\DatabaseException;
use KaririCode\Database\Platform\MySqlPlatform;
use KaririCode\Database\Platform\PostgreSqlPlatform;
use KaririCode\Database\Platform\SqlitePlatform;
use KaririCode\Database\Type\ScalarValue;
use KaririCode\Database\ValueObject\ConnectionParams;
use KaririCode\Database\ValueObject\PlatformVersion;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Builds a ready DefaultConnection from ConnectionParams: picks the PDO
 * driver for the driver name, wraps it in the middleware stack, opens the
 * session, detects the server version (MySQL or MariaDB, PostgreSQL,
 * SQLite) for the platform, and reports the connect as an event.
 *
 * Preconditions: middlewares are listed outermost first; ADR-002 asks for
 * Logging, Profiling, Retry, Tenancy, so a retried connect re-runs the
 * tenant switch. The driver name is pdo_mysql, pdo_pgsql or pdo_sqlite
 * (ConfigurationException 6020 otherwise).
 *
 * Thread-safety: immutable; safe to share. Each call opens a new session.
 *
 * Side effects: connects to the server; dispatches ConnectionEstablished
 * with the connection name, or ConnectionError when the connect fails,
 * when a PSR-14 dispatcher is given. The connection gets the same
 * dispatcher for its query and transaction events.
 *
 * Example:
 *
 *     $factory = new ConnectionFactory([new LoggingMiddleware($logger), new RetryMiddleware()], $dispatcher);
 *     $connection = $factory->createConnection((new DsnParser())->parse($url), 'default');
 *
 * References: ADR-002 (middleware order); ADR-003 (version-aware
 * platforms); ADR-013 (events); SPEC-001 §2.3 and §4.
 *
 * @see https://www.php-fig.org/psr/psr-14/
 */
final class ConnectionFactory
{
    /** @param array<int, Middleware> $middlewares */
    public function __construct(
        private readonly array $middlewares = [],
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {}

    public function createConnection(ConnectionParams $params, string $name = 'default'): DefaultConnection
    {
        $driverType = $params->driverType();
        $driver = $this->applyMiddlewares($this->createDriver($driverType));
        $start = hrtime(true);

        try {
            $driverConnection = $driver->connect($params);
        } catch (DatabaseException $e) {
            $this->eventDispatcher?->dispatch(new ConnectionError(exception: $e));

            throw $e;
        }

        $this->eventDispatcher?->dispatch(new ConnectionEstablished(
            connectionName: $name,
            driverType: $driverType->value,
            durationMs: (hrtime(true) - $start) / 1_000_000,
        ));
        $platform = $this->detectPlatform($driverConnection, $driverType);

        return new DefaultConnection(
            driverConnection: $driverConnection,
            platform: $platform,
            eventDispatcher: $this->eventDispatcher,
        );
    }

    private function createDriver(DriverType $type): Driver
    {
        return match ($type) {
            DriverType::PdoMysql => new PdoMySqlDriver(),
            DriverType::PdoPgsql => new PdoPgSqlDriver(),
            DriverType::PdoSqlite => new PdoSqliteDriver(),
        };
    }

    private function applyMiddlewares(Driver $driver): Driver
    {
        foreach (array_reverse($this->middlewares) as $middleware) {
            $driver = $middleware->wrap($driver);
        }

        return $driver;
    }

    private function detectPlatform(DriverConnection $conn, DriverType $driverType): Platform
    {
        $version = $this->detectVersion($conn, $driverType);

        return match ($driverType) {
            DriverType::PdoMysql => new MySqlPlatform($version),
            DriverType::PdoPgsql => new PostgreSqlPlatform($version),
            DriverType::PdoSqlite => new SqlitePlatform($version),
        };
    }

    private function detectVersion(DriverConnection $conn, DriverType $driverType): PlatformVersion
    {
        $native = $conn->nativeConnection();

        if (!$native instanceof \PDO) {
            return new PlatformVersion('unknown', 0);
        }

        $versionString = ScalarValue::asString($native->getAttribute(\PDO::ATTR_SERVER_VERSION), 'version');

        return match ($driverType) {
            DriverType::PdoMysql => $this->parseMySqlVersion($versionString),
            DriverType::PdoPgsql => $this->parsePostgreSqlVersion($versionString),
            DriverType::PdoSqlite => $this->parseSqliteVersion($versionString),
        };
    }

    private function parseMySqlVersion(string $version): PlatformVersion
    {
        $isMariaDb = stripos($version, 'mariadb') !== false;
        $vendor = $isMariaDb ? 'mariadb' : 'mysql';

        if (preg_match('/(\d+)\.(\d+)\.(\d+)/', $version, $m)) {
            return new PlatformVersion(vendor: $vendor, major: (int) $m[1], minor: (int) $m[2], patch: (int) $m[3]);
        }

        return new PlatformVersion($vendor, 0);
    }

    private function parsePostgreSqlVersion(string $version): PlatformVersion
    {
        if (preg_match('/(\d+)\.(\d+)/', $version, $m)) {
            return new PlatformVersion(vendor: 'postgresql', major: (int) $m[1], minor: (int) $m[2]);
        }

        return new PlatformVersion('postgresql', 0);
    }

    private function parseSqliteVersion(string $version): PlatformVersion
    {
        if (preg_match('/(\d+)\.(\d+)\.(\d+)/', $version, $m)) {
            return new PlatformVersion(vendor: 'sqlite', major: (int) $m[1], minor: (int) $m[2], patch: (int) $m[3]);
        }

        return new PlatformVersion('sqlite', 0);
    }
}
