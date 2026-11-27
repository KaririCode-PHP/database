<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;

/**
 * Stores 64-bit integers as BIGINT and reads them as strings, so values
 * beyond PHP_INT_MAX on 32-bit builds, or from unsigned columns, keep
 * every digit.
 *
 * Preconditions: the value is an integer or a numeric string.
 *
 * Thread-safety: stateless.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     (new BigIntType())->convertToPhpValue(9007199254740993, $platform);   // '9007199254740993'
 *
 * References: ADR-007; SPEC-001 §2.4.
 *
 * @see https://www.postgresql.org/docs/current/datatype-numeric.html
 */
final readonly class BigIntType implements TypeConverter
{
    #[\Override]
    public function convertToDatabaseValue(mixed $value, Platform $platform): ?string
    {
        return $value === null ? null : ScalarValue::asString($value, 'bigint');
    }

    #[\Override]
    public function convertToPhpValue(mixed $value, Platform $platform): ?string
    {
        return $value === null ? null : ScalarValue::asString($value, 'bigint');
    }

    /**
     * @param array<string, mixed> $column
     */
    #[\Override]
    public function getSqlDeclaration(array $column, Platform $platform): string
    {
        return 'BIGINT';
    }
}
