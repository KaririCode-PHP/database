<?php

declare(strict_types=1);

namespace KaririCode\Database\ValueObject;

use KaririCode\Database\Enum\DriverType;

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
        return DriverType::from($this->driver);
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
