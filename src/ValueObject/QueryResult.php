<?php

declare(strict_types=1);

namespace KaririCode\Database\ValueObject;

/**
 * One statement as ProfilingMiddleware recorded it: SQL, the parameters
 * passed to execute(), duration in milliseconds and row count.
 *
 * Preconditions: values bound one by one are not in $params.
 *
 * Thread-safety: immutable.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $slowest = array_reduce($profiler->profiles(), static fn(?QueryResult $max, QueryResult $p): QueryResult => $max === null || $p->executionTimeMs > $max->executionTimeMs ? $p : $max);
 *
 * References: ADR-002; SPEC-001 §2.1.
 *
 * @see https://www.php.net/manual/en/function.hrtime.php
 */
final readonly class QueryResult
{
    /**
     * @param array<int|string, mixed> $params
     */
    public function __construct(
        public string $sql,
        public array $params,
        public float $executionTimeMs,
        public int $rowCount,
    ) {}
}
