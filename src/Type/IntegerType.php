<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;

/**
 * Stores integers as INT and reads them as PHP int, refusing values that
 * would lose information: a fractional float or a non-numeric string
 * throws instead of becoming 0.
 *
 * Preconditions: the value is an integer, a whole float, a numeric string
 * or a bool.
 *
 * Thread-safety: stateless.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     (new IntegerType())->convertToPhpValue('42', $platform);   // 42
 *
 * References: ADR-007; SPEC-001 §2.4.
 *
 * @see https://www.php.net/manual/en/language.types.integer.php
 */
final readonly class IntegerType implements TypeConverter
{
    #[\Override]
    public function convertToDatabaseValue(mixed $value, Platform $platform): ?int
    {
        return $value === null ? null : ScalarValue::asInt($value, 'integer');
    }

    #[\Override]
    public function convertToPhpValue(mixed $value, Platform $platform): ?int
    {
        return $value === null ? null : ScalarValue::asInt($value, 'integer');
    }

    /**
     * @param array<string, mixed> $column
     */
    #[\Override]
    public function getSqlDeclaration(array $column, Platform $platform): string
    {
        return 'INT';
    }
}
