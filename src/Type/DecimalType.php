<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;

/**
 * Stores exact decimals as NUMERIC(precision, scale) on PostgreSQL and
 * DECIMAL(precision, scale) elsewhere, and keeps them as strings in PHP,
 * so money never passes through a float.
 *
 * Preconditions: the value is a numeric string, or a scalar that converts
 * to one; precision defaults to 10 and scale to 2. SQLite has no exact
 * decimal storage and keeps the number as REAL.
 *
 * Thread-safety: stateless.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     (new DecimalType())->getSqlDeclaration(['precision' => 12, 'scale' => 4], $platform);   // DECIMAL(12, 4)
 *
 * References: ADR-007; SPEC-001 §2.4.
 *
 * @see https://www.postgresql.org/docs/current/datatype-numeric.html#DATATYPE-NUMERIC-DECIMAL
 */
final readonly class DecimalType implements TypeConverter
{
    #[\Override]
    public function convertToDatabaseValue(mixed $value, Platform $platform): ?string
    {
        return $value === null ? null : ScalarValue::asString($value, 'decimal');
    }

    #[\Override]
    public function convertToPhpValue(mixed $value, Platform $platform): ?string
    {
        return $value === null ? null : ScalarValue::asString($value, 'decimal');
    }

    /**
     * @param array<string, mixed> $column
     */
    #[\Override]
    public function getSqlDeclaration(array $column, Platform $platform): string
    {
        $precision = ScalarValue::asInt($column['precision'] ?? 10, 'decimal');
        $scale = ScalarValue::asInt($column['scale'] ?? 2, 'decimal');

        $typeName = $platform->version->isPostgreSql() ? 'NUMERIC' : 'DECIMAL';

        return "{$typeName}({$precision}, {$scale})";
    }
}
