<?php

declare(strict_types=1);

namespace KaririCode\Database\Driver\Pdo;

use KaririCode\Database\Contract\Result;
use KaririCode\Database\Type\ScalarValue;

// ── PdoResult ────────────────────────────────────────────────────────
//
// Wraps PDOStatement as a Result. Implements both eager fetch
// (fetchAll*) and generator-backed lazy iteration (iterate*).
//
// Generator pattern ref: PHP Manual — Generators overview;
// Pogor (2024) "Efficient Data Processing with PHP Generators"
// ─────────────────────────────────────────────────────────────────────

final class PdoResult implements Result
{
    public function __construct(
        private \PDOStatement $stmt,
    ) {}

    // ── Eager Fetch ──────────────────────────────────────────────────────

    #[\Override]
    public function fetchAssociative(): array|false
    {
        return $this->nextAssociative() ?? false;
    }

    #[\Override]
    public function fetchNumeric(): array|false
    {
        return $this->nextNumeric() ?? false;
    }

    #[\Override]
    public function fetchOne(): mixed
    {
        return $this->stmt->fetchColumn();
    }

    #[\Override]
    public function fetchAllAssociative(): array
    {
        /** @var list<array<string, mixed>> $rows PDO::FETCH_ASSOC */
        $rows = $this->stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $rows;
    }

    #[\Override]
    public function fetchAllNumeric(): array
    {
        /** @var list<list<mixed>> $rows PDO::FETCH_NUM */
        $rows = $this->stmt->fetchAll(\PDO::FETCH_NUM);

        return $rows;
    }

    #[\Override]
    public function fetchFirstColumn(): array
    {
        /** @var list<mixed> $values PDO::FETCH_COLUMN */
        $values = $this->stmt->fetchAll(\PDO::FETCH_COLUMN);

        return $values;
    }

    // ── Indexed/KeyValue Fetch ───────────────────────────────────────────

    #[\Override]
    public function fetchAllKeyValue(): array
    {
        $rows = $this->fetchAllNumeric();

        return array_combine(
            array_map(static fn(array $row): int|string => ScalarValue::asKey($row[0] ?? null), $rows),
            array_map(static fn(array $row): mixed => $row[1] ?? null, $rows),
        );
    }

    #[\Override]
    public function fetchAllAssociativeIndexed(): array
    {
        $rows = $this->fetchAllAssociative();

        return array_combine(
            array_map(static fn(array $row): int|string => ScalarValue::asKey(reset($row)), $rows),
            array_map(static fn(array $row): array => array_slice($row, 1), $rows),
        );
    }

    // ── Lazy Iteration (O(1) memory) ─────────────────────────────────────

    #[\Override]
    public function iterateAssociative(): \Generator
    {
        while (($row = $this->nextAssociative()) !== null) {
            yield $row;
        }
    }

    #[\Override]
    public function iterateNumeric(): \Generator
    {
        while (($row = $this->nextNumeric()) !== null) {
            yield $row;
        }
    }

    #[\Override]
    public function iterateColumn(int $index = 0): \Generator
    {
        while (($row = $this->nextNumeric()) !== null) {
            yield $row[$index] ?? null;
        }
    }

    #[\Override]
    public function iterateKeyValue(): \Generator
    {
        while (($row = $this->nextNumeric()) !== null) {
            yield ScalarValue::asKey($row[0] ?? null) => $row[1] ?? null;
        }
    }

    #[\Override]
    public function iterateAssociativeIndexed(): \Generator
    {
        while (($row = $this->nextAssociative()) !== null) {
            yield ScalarValue::asKey(reset($row)) => array_slice($row, 1);
        }
    }

    #[\Override]
    public function rowCount(): int
    {
        return $this->stmt->rowCount();
    }

    #[\Override]
    public function columnCount(): int
    {
        return $this->stmt->columnCount();
    }

    #[\Override]
    public function free(): void
    {
        $this->stmt->closeCursor();
    }

    // ── Leitura tipada ───────────────────────────────────────────────────

    /** @return array<string, mixed>|null */
    private function nextAssociative(): ?array
    {
        /** @var array<string, mixed>|false $row PDO::FETCH_ASSOC */
        $row = $this->stmt->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return list<mixed>|null */
    private function nextNumeric(): ?array
    {
        /** @var list<mixed>|false $row PDO::FETCH_NUM */
        $row = $this->stmt->fetch(\PDO::FETCH_NUM);

        return $row === false ? null : $row;
    }
}
