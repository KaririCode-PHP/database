<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Exception\TypeConversionException;

/**
 * Single point where loosely typed values (driver results, cache entries,
 * catalog rows) become PHP scalars. A value that cannot be represented
 * fails with TypeConversionException instead of being silently coerced
 * ((int) 'abc' used to become 0).
 *
 * Preconditions: $dbType names the target type for the error message.
 *
 * Thread-safety: stateless.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $length = ScalarValue::asInt($row['CHARACTER_MAXIMUM_LENGTH'], 'integer');
 *
 * References: Spec V4.3.3 §8.4 (failures as a contract family, Runtime);
 * ADR-007.
 *
 * @see https://www.php.net/manual/en/language.types.type-juggling.php
 * @since 4.3.0
 */
final class ScalarValue
{
    private function __construct() {}

    public static function asString(mixed $value, string $dbType): string
    {
        return match (true) {
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            is_bool($value) => $value ? '1' : '0',
            $value instanceof \Stringable => (string) $value,
            default => throw TypeConversionException::conversionFailed(phpType: get_debug_type($value), dbType: $dbType),
        };
    }

    public static function asInt(mixed $value, string $dbType): int
    {
        return match (true) {
            is_int($value) => $value,
            is_bool($value) => $value ? 1 : 0,
            is_float($value) && floor($value) === $value => (int) $value,
            is_string($value) && preg_match('/^\s*[+-]?\d+\s*$/', $value) === 1 => (int) $value,
            default => throw TypeConversionException::conversionFailed(phpType: get_debug_type($value), dbType: $dbType),
        };
    }

    public static function asFloat(mixed $value, string $dbType): float
    {
        return match (true) {
            is_float($value) => $value,
            is_int($value) => (float) $value,
            is_string($value) && is_numeric($value) => (float) $value,
            default => throw TypeConversionException::conversionFailed(phpType: get_debug_type($value), dbType: $dbType),
        };
    }

    public static function asBool(mixed $value, string $dbType): bool
    {
        return match (true) {
            is_bool($value) => $value,
            is_int($value) => $value !== 0,
            is_string($value) && in_array(strtolower($value), ['1', 't', 'true', 'y', 'yes', 'on'], true) => true,
            is_string($value) && in_array(strtolower($value), ['0', 'f', 'false', 'n', 'no', 'off', ''], true) => false,
            default => throw TypeConversionException::conversionFailed(phpType: get_debug_type($value), dbType: $dbType),
        };
    }

    /** A value usable as an array key; other scalars become their string form. */
    public static function asKey(mixed $value): int|string
    {
        return is_int($value) || is_string($value) ? $value : self::asString($value, 'key');
    }
}
