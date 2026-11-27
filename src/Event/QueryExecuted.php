<?php

declare(strict_types=1);

namespace KaririCode\Database\Event;

use KaririCode\Database\Contract\DataStoreEvent;

/**
 * One statement that ran through DefaultConnection: SQL, bound values and
 * types, duration in milliseconds and row count.
 *
 * Preconditions: the row count is what the driver reports: affected rows
 * for DML; for a SELECT, PostgreSQL and buffered MySQL report the rows,
 * SQLite reports 0.
 *
 * Thread-safety: immutable.
 *
 * Side effects: none, but listeners receive the bound values; redact them
 * before logging.
 *
 * Example:
 *
 *     if ($event instanceof QueryExecuted && $event->executionTimeMs > 200.0) {
 *         $logger->warning('Slow query.', ['sql' => $event->sql, 'ms' => $event->executionTimeMs]);
 *     }
 *
 * References: ADR-013; SPEC-001 §2.3.
 *
 * @see https://www.php-fig.org/psr/psr-14/
 */
final readonly class QueryExecuted implements DataStoreEvent
{
    /**
     * @param array<int|string, int> $types
     * @param array<int|string, mixed> $params
     */
    public function __construct(
        public string $sql,
        public array $params,
        public array $types,
        public float $executionTimeMs,
        public int $rowCount,
    ) {}
}
