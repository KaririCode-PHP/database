<?php

declare(strict_types=1);

namespace KaririCode\Database\ValueObject;

use KaririCode\Database\Enum\DriverType;
use KaririCode\Database\Exception\ConfigurationException;

/**
 * Everything needed to open a connection: driver, host, port, database,
 * credentials, charset, free options and PDO attributes. The password can
 * be read but is masked in var_dump() and stack traces.
 *
 * Preconditions: driver is pdo_mysql, pdo_pgsql or pdo_sqlite (6020 from
 * driverType() otherwise); driverOptions are PDO attributes keyed by their
 * PDO::ATTR_* constant and replace the driver's defaults key by key.
 *
 * Thread-safety: immutable.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $params = new ConnectionParams(driver: 'pdo_pgsql', host: 'db', port: 5432, database: 'shop', username: 'app', password: $secret);
 *
 * References: ADR-011 (DSN parser); SPEC-001 §3.
 *
 * @see https://www.php.net/manual/en/class.sensitiveparameter.php
 */
final readonly class ConnectionParams
{
    /**
     * @param array<int, mixed> $driverOptions
     * @param array<string, mixed> $options
     */
    public function __construct(
        public string $driver,
        public string $host = 'localhost',
        public int $port = 3306,
        public string $database = '',
        public string $username = '',
        #[\SensitiveParameter]
        public private(set) string $password = '',
        public string $charset = 'utf8mb4',
        public array $options = [],
        public array $driverOptions = [],
    ) {}

    public function driverType(): DriverType
    {
        return DriverType::tryFrom($this->driver) ?? throw ConfigurationException::unknownDriver();
    }

    public function __debugInfo(): array
    {
        return [
            'driver' => $this->driver,
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => '********',
            'charset' => $this->charset,
        ];
    }
}
