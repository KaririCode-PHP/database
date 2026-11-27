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
use KaririCode\Database\Platform\MySqlPlatform;
use KaririCode\Database\Platform\PostgreSqlPlatform;
use KaririCode\Database\Platform\SqlitePlatform;
use KaririCode\Database\Type\ScalarValue;
use KaririCode\Database\ValueObject\ConnectionParams;
use KaririCode\Database\ValueObject\PlatformVersion;
use Psr\EventDispatcher\EventDispatcherInterface;

// ── ConnectionFactory ────────────────────────────────────────────────
//
// Creates Connection instances from ConnectionParams. Applies
// middleware stack in LIFO order (first registered = outermost).
//
// Required middleware order (outermost → innermost):
//   Logging → Profiling → Retry → Tenancy → Driver
// ─────────────────────────────────────────────────────────────────────

final class ConnectionFactory
{
    /** @param array<int, Middleware> $middlewares */
    public function __construct(
        private readonly array $middlewares = [],
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {}

    public function createConnection(ConnectionParams $params): DefaultConnection
    {
        $driverType = $params->driverType();
        $driver = $this->createDriver($driverType);

        $wrappedDriver = $this->applyMiddlewares($driver);
        $driverConnection = $wrappedDriver->connect($params);
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
