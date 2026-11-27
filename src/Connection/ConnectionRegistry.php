<?php

declare(strict_types=1);

namespace KaririCode\Database\Connection;

use KaririCode\Database\Contract\Connection;
use KaririCode\Database\DsnParser;
use KaririCode\Database\Exception\ConfigurationException;
use KaririCode\Database\ValueObject\ConnectionParams;

// ── ConnectionRegistry ───────────────────────────────────────────────
//
// Named connection registry with lazy initialization.
// Supports both ConnectionParams and DSN string configuration.
// ─────────────────────────────────────────────────────────────────────

final class ConnectionRegistry
{
    /** @var array<string, Connection> */
    private array $connections = [];

    /**
     * @param array<string, ConnectionParams|string> $configs Keyed by name. String values are parsed as DSN.
     */
    public function __construct(
        private readonly array $configs,
        private readonly ConnectionFactory $factory = new ConnectionFactory(),
    ) {}

    public function get(string $name): Connection
    {
        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        if (!isset($this->configs[$name])) {
            throw ConfigurationException::connectionNotConfigured();
        }

        $config = $this->configs[$name];

        if (is_string($config)) {
            $config = (new DsnParser())->parse($config);
        }

        $this->connections[$name] = $this->factory->createConnection($config);

        return $this->connections[$name];
    }

    public function defaultConnection(): Connection
    {
        return $this->get('default');
    }

    public function has(string $name): bool
    {
        return isset($this->configs[$name]);
    }

    public function disconnect(string $name): void
    {
        unset($this->connections[$name]);
    }

    public function disconnectAll(): void
    {
        $this->connections = [];
    }

    /** @return array<int, string> */
    public function names(): array
    {
        return array_keys($this->configs);
    }

    /** @return array<string, bool> */
    public function healthCheck(): array
    {
        $results = [];

        foreach ($this->configs as $name => $_) {
            try {
                $results[$name] = $this->get($name)->ping();
            } catch (\Throwable) {
                $results[$name] = false;
            }
        }

        return $results;
    }
}
