<?php

declare(strict_types=1);

namespace KaririCode\Database\Middleware;

use KaririCode\Database\Contract\Driver;
use KaririCode\Database\Contract\DriverConnection;
use KaririCode\Database\Contract\Middleware;
use KaririCode\Database\Contract\Statement;
use KaririCode\Database\Contract\Result;
use KaririCode\Database\ValueObject\ConnectionParams;

// ── TenancyMiddleware ────────────────────────────────────────────────
//
// Executes a SQL statement on every connect() — typically
// SET search_path TO ? (PostgreSQL) or USE ? (MySQL).
//
// Must be the innermost middleware so reconnections from
// RetryMiddleware re-execute the schema switch.
//
// Ref: ADR-002 ordering constraint.
// ─────────────────────────────────────────────────────────────────────

final class TenancyMiddleware implements Middleware
{
    /** @param \Closure(): ?string $schemaResolver Returns current tenant schema or null */
    public function __construct(
        private readonly \Closure $schemaResolver,
    ) {}

    #[\Override]
    public function wrap(Driver $driver): Driver
    {
        $resolver = $this->schemaResolver;

        return new class ($driver, $resolver) implements Driver {
            /** @param \Closure(): ?string $schemaResolver */
            public function __construct(
                private readonly Driver $inner,
                private readonly \Closure $schemaResolver,
            ) {}

            #[\Override]
            public function connect(ConnectionParams $params): DriverConnection
            {
                $conn = $this->inner->connect($params);

                $schema = ($this->schemaResolver)();

                if (is_string($schema) && $schema !== '') {
                    $driverType = $params->driverType()->value;
                    // Escape identifiers to prevent SQL injection via schema name
                    $sql = match (true) {
                        str_contains($driverType, 'pgsql') => 'SET search_path TO "' . str_replace('"', '""', $schema) . '", public',
                        str_contains($driverType, 'mysql') => 'USE `' . str_replace('`', '``', $schema) . '`',
                        default => null,
                    };

                    if ($sql !== null) {
                        $conn->exec($sql);
                    }
                }

                return $conn;
            }
        };
    }
}
