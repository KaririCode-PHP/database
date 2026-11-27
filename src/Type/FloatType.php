<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;

/**
 * Stores double-precision floats: DOUBLE PRECISION on PostgreSQL, DOUBLE
 * elsewhere.
 *
 * Preconditions: the value is a float, an integer or a numeric string; for
 * exact amounts use DecimalType.
 *
 * Thread-safety: stateless.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     (new FloatType())->convertToPhpValue('1.5', $platform);   // 1.5
 *
 * References: ADR-007; SPEC-001 §2.4.
 *
 * @see https://www.postgresql.org/docs/current/datatype-numeric.html#DATATYPE-FLOAT
 */
final readonly class FloatType implements TypeConverter
{
    #[\Override]
    public function convertToDatabaseValue(mixed $value, Platform $platform): ?float
    {
        return $value === null ? null : ScalarValue::asFloat($value, 'float');
    }

    #[\Override]
    public function convertToPhpValue(mixed $value, Platform $platform): ?float
    {
        return $value === null ? null : ScalarValue::asFloat($value, 'float');
    }

    /**
     * @param array<string, mixed> $column
     */
    #[\Override]
    public function getSqlDeclaration(array $column, Platform $platform): string
    {
        $version = $platform->version;

        if ($version->isPostgreSql()) {
            return 'DOUBLE PRECISION';
        }

        return 'DOUBLE';
    }
}
