<?php

declare(strict_types=1);

namespace KaririCode\Database\Contract;

// ── Result ───────────────────────────────────────────────────────────
//
// Abstraction over database result sets. Supports eager
// materialization (fetchAll*) and generator-backed iteration
// (iterate*) for O(1) memory ETL pipelines.
//
// fetchAllKeyValue / fetchAllAssociativeIndexed match
// Doctrine DBAL 4.4 Connection convenience methods.
// ─────────────────────────────────────────────────────────────────────

interface Result
{
    // ── Eager Fetch ──────────────────────────────────────────────────────

    /** @return array<string, mixed>|false */
    public function fetchAssociative(): array|false;

    /** @return array<int, mixed>|false */
    public function fetchNumeric(): array|false;

    public function fetchOne(): mixed;

    /** @return array<int, array<string, mixed>> */
    public function fetchAllAssociative(): array;

    /** @return array<int, array<int, mixed>> */
    public function fetchAllNumeric(): array;

    /** @return array<int, mixed> */
    public function fetchFirstColumn(): array;

    /**
     * First column = key, second column = value.
     * Ref: Doctrine DBAL 4.4 fetchAllKeyValue().
     *
     * @return array<mixed, mixed>
     */
    public function fetchAllKeyValue(): array;

    /**
     * First column = key, remaining columns = assoc row.
     * Ref: Doctrine DBAL 4.4 fetchAllAssociativeIndexed().
     *
     * @return array<mixed, array<string, mixed>>
     */
    public function fetchAllAssociativeIndexed(): array;

    // ── Lazy Iteration (generator-backed, O(1) memory) ───────────────────

    /** @return \Generator<int, array<string, mixed>> */
    public function iterateAssociative(): \Generator;

    /** @return \Generator<int, array<int, mixed>> */
    public function iterateNumeric(): \Generator;

    /** @return \Generator<int, mixed> */
    public function iterateColumn(int $index = 0): \Generator;

    /** @return \Generator<mixed, mixed> */
    public function iterateKeyValue(): \Generator;

    /** @return \Generator<mixed, array<string, mixed>> */
    public function iterateAssociativeIndexed(): \Generator;

    // ── Metadata ─────────────────────────────────────────────────────────

    public function rowCount(): int;

    public function columnCount(): int;

    public function free(): void;
}
