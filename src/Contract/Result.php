<?php

declare(strict_types=1);

namespace KaririCode\Database\Contract;

/**
 * The rows a query returned, read eagerly as lists or lazily through
 * generators, in associative, numeric, first-column, key-value or indexed
 * shape.
 *
 * Preconditions: one cursor per result, so mixing fetch*() and iterate*()
 * continues from the current row; key-value and indexed shapes need a
 * first column that can be an array key.
 *
 * Thread-safety: not safe to share.
 *
 * Side effects: fetching advances the cursor; free() releases it.
 *
 * Example:
 *
 *     foreach ($connection->executeQuery('SELECT * FROM audit_log')->iterateAssociative() as $row) {
 *         $export->write($row);
 *     }
 *
 * References: SPEC-001 §2.1 (ISSUE-002, lazy iteration).
 *
 * @see https://www.php.net/manual/en/language.generators.overview.php
 */
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
