<?php

declare(strict_types=1);

namespace KaririCode\Database\ValueObject;

use KaririCode\Database\Exception\QueryException;
use KaririCode\Database\Type\ScalarValue;

// ── ResultRow ────────────────────────────────────────────────────────

/**
 * Typed read access to one fetched row (catalog queries, introspection),
 * so a missing column or an unexpected value type fails loudly instead
 * of flowing on as mixed.
 *
 * Preconditions: every column read is present in the row; a missing
 *   column throws QueryException (6041). A NULL read through a
 *   non-nullable accessor becomes '' / 0 / false.
 * Side effects: none.
 * Thread-safety: immutable.
 *
 * Example:
 *   $row = new ResultRow(['COLUMN_NAME' => 'id', 'NON_UNIQUE' => 0]);
 *   $unique = $row->int('NON_UNIQUE') === 0;
 *
 * Spec V4.3.3 §9.1: final readonly value object.
 * @see https://www.php.net/manual/en/pdostatement.fetch.php
 * @since 4.3.0
 */
final readonly class ResultRow
{
    /** @param array<array-key, mixed> $values */
    public function __construct(private array $values) {}

    public function string(string $column): string
    {
        return ScalarValue::asString($this->value($column) ?? '', 'string');
    }

    public function nullableString(string $column): ?string
    {
        return $this->isNull($column) ? null : ScalarValue::asString($this->value($column), 'string');
    }

    public function int(string $column): int
    {
        return ScalarValue::asInt($this->value($column) ?? 0, 'integer');
    }

    public function nullableInt(string $column): ?int
    {
        return $this->isNull($column) ? null : ScalarValue::asInt($this->value($column), 'integer');
    }

    public function bool(string $column): bool
    {
        return ScalarValue::asBool($this->value($column) ?? false, 'boolean');
    }

    private function isNull(string $column): bool
    {
        return $this->value($column) === null;
    }

    /** Coluna ausente e erro na forma da consulta, nunca um valor vazio. */
    private function value(string $column): mixed
    {
        if (!array_key_exists($column, $this->values)) {
            throw QueryException::missingColumn();
        }

        return $this->values[$column];
    }
}
