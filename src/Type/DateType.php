<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;
use KaririCode\Database\Exception\TypeConversionException;

/**
 * Stores a calendar date as 'Y-m-d': DATE on the servers, text on SQLite.
 * It is read back at midnight in PHP's default time zone.
 *
 * Preconditions: the value is a \DateTimeInterface; its time is ignored.
 *
 * Thread-safety: stateless.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     (new DateType())->convertToPhpValue('2026-11-27', $platform)?->format('Y-m-d');   // '2026-11-27'
 *
 * References: ADR-007; SPEC-001 §2.4.
 *
 * @see https://www.php.net/manual/en/datetimeimmutable.createfromformat.php
 */
final readonly class DateType implements TypeConverter
{
    private const FORMAT = 'Y-m-d';

    #[\Override]
    public function convertToDatabaseValue(mixed $value, Platform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(self::FORMAT);
        }

        throw TypeConversionException::conversionFailed(get_debug_type($value), 'date');
    }

    #[\Override]
    public function convertToPhpValue(mixed $value, Platform $platform): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $dt = \DateTimeImmutable::createFromFormat('!' . self::FORMAT, ScalarValue::asString($value, 'date'));

        return $dt ?: new \DateTimeImmutable(ScalarValue::asString($value, 'date'));
    }

    /**
     * @param array<string, mixed> $column
     */
    #[\Override]
    public function getSqlDeclaration(array $column, Platform $platform): string
    {
        if ($platform->version->isSqlite()) {
            return 'TEXT';
        }

        return 'DATE';
    }
}
