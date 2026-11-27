<?php

declare(strict_types=1);

namespace KaririCode\Database\ValueObject;

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
