<?php

declare(strict_types=1);

namespace KaririCode\Database\Driver\Pdo;

use KaririCode\Database\Contract\ExceptionConverter;
use KaririCode\Database\Contract\Result;
use KaririCode\Database\Type\ScalarValue;

/**
 * The rows of one executed PDO statement, read eagerly (fetchAll*) or one
 * at a time through generators (iterate*), so a large result streams in
 * constant memory. A driver error raised while fetching (SQLite evaluates
 * rows lazily, so a malformed JSON value in row 2 fails on the second
 * fetch) is converted like a statement error.
 *
 * Preconditions: a result is read once; the generators and the fetch
 * methods share one cursor. Key-value and indexed reads need a first
 * column that can be an array key (ScalarValue::asKey()).
 *
 * Thread-safety: not safe to share; it holds the statement's cursor.
 *
 * Side effects: fetching advances the cursor and may pull rows from the
 * server; free() closes the cursor so the connection can run the next
 * statement (needed on MySQL with unbuffered queries).
 *
 * Example:
 *
 *     foreach ($connection->executeQuery('SELECT id, name FROM users')->iterateKeyValue() as $id => $name) {
 *         $names[$id] = $name;
 *     }
 *
 * References: ADR-001; ADR-004; SPEC-001 §2.1 (Result, lazy iteration).
 *
 * @see https://www.php.net/manual/en/pdostatement.fetch.php
 */
final class PdoResult implements Result
{
    public function __construct(
        private readonly \PDOStatement $stmt,
        private readonly ExceptionConverter $converter,
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
        return $this->guarded(fn(): mixed => $this->stmt->fetchColumn());
    }

    #[\Override]
    public function fetchAllAssociative(): array
    {
        /** @var list<array<string, mixed>> $rows PDO::FETCH_ASSOC */
        $rows = $this->guarded(fn(): array => $this->stmt->fetchAll(\PDO::FETCH_ASSOC));

        return $rows;
    }

    #[\Override]
    public function fetchAllNumeric(): array
    {
        /** @var list<list<mixed>> $rows PDO::FETCH_NUM */
        $rows = $this->guarded(fn(): array => $this->stmt->fetchAll(\PDO::FETCH_NUM));

        return $rows;
    }

    #[\Override]
    public function fetchFirstColumn(): array
    {
        /** @var list<mixed> $values PDO::FETCH_COLUMN */
        $values = $this->guarded(fn(): array => $this->stmt->fetchAll(\PDO::FETCH_COLUMN));

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
        $row = $this->guarded(fn(): mixed => $this->stmt->fetch(\PDO::FETCH_ASSOC));

        return $row === false ? null : $row;
    }

    /** @return list<mixed>|null */
    private function nextNumeric(): ?array
    {
        /** @var list<mixed>|false $row PDO::FETCH_NUM */
        $row = $this->guarded(fn(): mixed => $this->stmt->fetch(\PDO::FETCH_NUM));

        return $row === false ? null : $row;
    }

    /**
     * @template T
     * @param \Closure(): T $fetch
     * @return T
     */
    private function guarded(\Closure $fetch): mixed
    {
        try {
            $fetched = $fetch();
        } catch (\PDOException $e) {
            throw $this->converter->convert($e, $this->stmt->queryString);
        }

        // fetchAll() do pdo_sqlite devolve as linhas lidas ate o erro sem lancar:
        // o erro so fica em errorInfo(), e o resultado parcial passaria por completo.
        $info = $this->stmt->errorInfo();
        $state = $info[0] ?? null;
        if (is_string($state) && $state !== '00000') {
            $detail = $info[2] ?? null;
            $error = new \PDOException(sprintf('SQLSTATE[%s]: %s', $state, is_string($detail) ? $detail : ''));
            $error->errorInfo = $info;

            throw $this->converter->convert($error, $this->stmt->queryString);
        }

        return $fetched;
    }
}
