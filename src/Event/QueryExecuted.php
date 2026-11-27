<?php

declare(strict_types=1);

namespace KaririCode\Database\Event;

use KaririCode\Database\Contract\DataStoreEvent;

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
