<?php

declare(strict_types=1);

namespace KaririCode\Database\Connection;

use KaririCode\Database\Contract\Connection;
use KaririCode\Database\DsnParser;
use KaririCode\Database\Exception\ConfigurationException;
use KaririCode\Database\ValueObject\ConnectionParams;

/**
 * Named connections built on first use from ConnectionParams or DSN
 * strings, so an application configures every database in one place and
 * opens only those a request touches.
 *
 * Preconditions: names are configured up front; an unknown name is
 * ConfigurationException 6018, a wiring error rather than a missing
 * record.
 *
 * Thread-safety: caches the connections it opened; one registry per
 * request or worker.
 *
 * Side effects: get() opens the connection on first use; healthCheck()
 * opens every configured connection and pings it; disconnect() drops the
 * cached connection.
 *
 * Example:
 *
 *     $registry = new ConnectionRegistry([
 *         'default' => (string) getenv('DATABASE_URL'),
 *         'reporting' => new ConnectionParams(driver: 'pdo_pgsql', host: 'replica', database: 'shop'),
 *     ]);
 *     $rows = $registry->get('reporting')->executeQuery('SELECT 1')->fetchAllNumeric();
 *
 * References: ADR-011 (DSN); SPEC-001 §4.4 (health check).
 *
 * @see https://www.rfc-editor.org/rfc/rfc3986
 */
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

        $this->connections[$name] = $this->factory->createConnection($config, $name);

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
