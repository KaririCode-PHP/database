<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;
use KaririCode\Database\Exception\TypeConversionException;

/**
 * Stores a \DateInterval as its ISO 8601 duration, with a leading "-" for
 * a negative interval, in a VARCHAR(255) on every platform. A native
 * PostgreSQL INTERVAL is not used: the server answers in its own output
 * style ("1 year 2 mons 3 days 04:05:06"), which \DateInterval cannot
 * read.
 *
 * Preconditions: the PHP value is a \DateInterval or null; a stored value
 * is a duration this type wrote. Fractions of a second are not kept.
 *
 * Thread-safety: stateless.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $type = new DateIntervalType();
 *     $type->convertToDatabaseValue(new \DateInterval('P1DT2H'), $platform);   // 'P0Y0M1DT2H0M0S'
 *
 * References: ADR-007 (type registry); SPEC-001 §2.4.
 *
 * @see https://www.php.net/manual/en/dateinterval.construct.php
 */
final readonly class DateIntervalType implements TypeConverter
{
    #[\Override]
    public function convertToDatabaseValue(mixed $value, Platform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateInterval) {
            // invert: sem o sinal, um intervalo negativo voltava positivo.
            return ($value->invert === 1 ? '-' : '') . 'P' . $value->y . 'Y' . $value->m . 'M' . $value->d . 'DT'
                . $value->h . 'H' . $value->i . 'M' . $value->s . 'S';
        }

        throw TypeConversionException::conversionFailed(get_debug_type($value), 'dateinterval');
    }

    #[\Override]
    public function convertToPhpValue(mixed $value, Platform $platform): ?\DateInterval
    {
        if ($value === null || $value === '') {
            return null;
        }

        $duration = ScalarValue::asString($value, 'dateinterval');
        $negative = str_starts_with($duration, '-');
        $interval = new \DateInterval($negative ? substr($duration, 1) : $duration);
        $interval->invert = $negative ? 1 : 0;

        return $interval;
    }

    /**
     * @param array<string, mixed> $column
     */
    #[\Override]
    public function getSqlDeclaration(array $column, Platform $platform): string
    {
        return 'VARCHAR(255)';
    }
}
