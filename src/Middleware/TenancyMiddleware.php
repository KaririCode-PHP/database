<?php

declare(strict_types=1);

namespace KaririCode\Database\Middleware;

use KaririCode\Database\Contract\Driver;
use KaririCode\Database\Contract\DriverConnection;
use KaririCode\Database\Contract\Middleware;
use KaririCode\Database\Contract\Statement;
use KaririCode\Database\Contract\Result;
use KaririCode\Database\ValueObject\ConnectionParams;

/**
 * Points each new session at the current tenant: SET search_path on
 * PostgreSQL, USE on MySQL, with the schema name quoted. SQLite has no
 * schemas, so nothing runs there.
 *
 * Preconditions: the resolver returns the tenant's schema or database
 * name, or null or '' for none. It runs on every connect, so it must be
 * cheap.
 *
 * Thread-safety: holds only the resolver; as safe as the resolver.
 *
 * Side effects: runs one statement per connect.
 *
 * Example:
 *
 *     $factory = new ConnectionFactory([new RetryMiddleware(), new TenancyMiddleware(static fn(): ?string => $tenantContext->schema())]);
 *
 * References: ADR-002 (innermost, after Retry); SPEC-001 §2.1.
 *
 * @see https://www.postgresql.org/docs/current/ddl-schemas.html#DDL-SCHEMAS-PATH
 */
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
