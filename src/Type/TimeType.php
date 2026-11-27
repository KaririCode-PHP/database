<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;
use KaririCode\Database\Exception\TypeConversionException;

/**
 * Stores a time of day as 'H:i:s': TIME on the servers, text on SQLite. It
 * is read back on 1970-01-01 in PHP's default time zone.
 *
 * Preconditions: the value is a \DateTimeInterface; its date is ignored
 * and fractions of a second are dropped.
 *
 * Thread-safety: stateless.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     (new TimeType())->convertToDatabaseValue(new \DateTimeImmutable('08:30'), $platform);   // '08:30:00'
 *
 * References: ADR-007; SPEC-001 §2.4.
 *
 * @see https://www.postgresql.org/docs/current/datatype-datetime.html
 */
final readonly class TimeType implements TypeConverter
{
    private const FORMAT = 'H:i:s';

    #[\Override]
    public function convertToDatabaseValue(mixed $value, Platform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(self::FORMAT);
        }

        throw TypeConversionException::conversionFailed(get_debug_type($value), 'time');
    }

    #[\Override]
    public function convertToPhpValue(mixed $value, Platform $platform): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $dt = \DateTimeImmutable::createFromFormat('!' . self::FORMAT, ScalarValue::asString($value, 'time'));

        return $dt ?: new \DateTimeImmutable(ScalarValue::asString($value, 'time'));
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

        return 'TIME';
    }
}
