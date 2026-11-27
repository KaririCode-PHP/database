<?php

declare(strict_types=1);

namespace KaririCode\Database\Cache;

use KaririCode\Database\Contract\Result;
use KaririCode\Database\Type\ScalarValue;

// ── CachedResult ─────────────────────────────────────────────────────
//
// Wraps materialized result array from cache. All fetch/iterate
// methods operate on the in-memory array.
// ─────────────────────────────────────────────────────────────────────

final class CachedResult implements Result
{
    private int $position = 0;

    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(
        private array $rows,
    ) {}

    #[\Override]
    public function fetchAssociative(): array|false
    {
        if (!isset($this->rows[$this->position])) {
            return false;
        }

        return $this->rows[$this->position++];
    }

    #[\Override]
    public function fetchNumeric(): array|false
    {
        $row = $this->fetchAssociative();

        return $row === false ? false : array_values($row);
    }

    #[\Override]
    public function fetchOne(): mixed
    {
        $row = $this->fetchAssociative();

        if ($row === false) {
            return false;
        }

        return reset($row);
    }

    #[\Override]
    public function fetchAllAssociative(): array
    {
        $remaining = array_slice($this->rows, $this->position);
        $this->position = count($this->rows);

        return $remaining;
    }

    #[\Override]
    public function fetchAllNumeric(): array
    {
        return array_map('array_values', $this->fetchAllAssociative());
    }

    #[\Override]
    public function fetchFirstColumn(): array
    {
        return array_map(
            fn(array $row) => reset($row),
            $this->fetchAllAssociative(),
        );
    }

    #[\Override]
    public function fetchAllKeyValue(): array
    {
        return array_combine(
            array_map(static fn(array $row): int|string => ScalarValue::asKey(array_values($row)[0] ?? null), $this->rows),
            array_map(static fn(array $row): mixed => array_values($row)[1] ?? null, $this->rows),
        );
    }

    #[\Override]
    public function fetchAllAssociativeIndexed(): array
    {
        $result = [];
        foreach ($this->rows as $row) {
            $result[ScalarValue::asKey(reset($row))] = array_slice($row, 1);
        }

        return $result;
    }

    #[\Override]
    public function iterateAssociative(): \Generator
    {
        foreach ($this->rows as $row) {
            yield $row;
        }
    }

    #[\Override]
    public function iterateNumeric(): \Generator
    {
        foreach ($this->rows as $row) {
            yield array_values($row);
        }
    }

    #[\Override]
    public function iterateColumn(int $index = 0): \Generator
    {
        foreach ($this->rows as $row) {
            $values = array_values($row);
            yield $values[$index] ?? null;
        }
    }

    #[\Override]
    public function iterateKeyValue(): \Generator
    {
        foreach ($this->rows as $row) {
            $values = array_values($row);
            yield $values[0] => $values[1] ?? null;
        }
    }

    #[\Override]
    public function iterateAssociativeIndexed(): \Generator
    {
        foreach ($this->rows as $row) {
            yield ScalarValue::asKey(reset($row)) => array_slice($row, 1);
        }
    }

    #[\Override]
    public function rowCount(): int
    {
        return count($this->rows);
    }

    #[\Override]
    public function columnCount(): int
    {
        if (empty($this->rows)) {
            return 0;
        }

        return count($this->rows[0]);
    }

    #[\Override]
    public function free(): void
    {
        $this->rows = [];
        $this->position = 0;
    }
}
