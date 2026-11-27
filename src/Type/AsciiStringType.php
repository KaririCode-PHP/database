<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;

/**
 * Stores ASCII text, such as codes and identifiers, as VARCHAR(length) on
 * every platform.
 *
 * Preconditions: the value is a string or a scalar that converts to one;
 * the column length defaults to 255. ASCII is the caller's promise: the
 * type does not check it.
 *
 * Thread-safety: stateless.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     (new AsciiStringType())->getSqlDeclaration(['length' => 3], $platform);   // VARCHAR(3)
 *
 * References: ADR-007 (type registry); SPEC-001 §2.4.
 *
 * @see https://dev.mysql.com/doc/refman/8.4/en/char.html
 */
final readonly class AsciiStringType implements TypeConverter
{
    #[\Override]
    public function convertToDatabaseValue(mixed $value, Platform $platform): ?string
    {
        return $value === null ? null : ScalarValue::asString($value, 'ascii_string');
    }

    #[\Override]
    public function convertToPhpValue(mixed $value, Platform $platform): ?string
    {
        return $value === null ? null : ScalarValue::asString($value, 'ascii_string');
    }

    /**
     * @param array<string, mixed> $column
     */
    #[\Override]
    public function getSqlDeclaration(array $column, Platform $platform): string
    {
        $length = ScalarValue::asInt($column['length'] ?? 255, 'ascii_string');
        return "VARCHAR({$length})";
    }
}
