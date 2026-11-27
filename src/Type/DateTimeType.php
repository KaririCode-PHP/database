<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;
use KaririCode\Database\Exception\TypeConversionException;

/**
 * Stores a wall-clock date and time without time zone, as
 * 'Y-m-d H:i:s': TIMESTAMP WITHOUT TIME ZONE on PostgreSQL, DATETIME on
 * MySQL, text on SQLite. It is read back in PHP's default time zone.
 *
 * Preconditions: the value is a \DateTimeInterface; its time zone is not
 * stored, so write values in one agreed zone (UTC). Fractions of a second
 * are dropped.
 *
 * Thread-safety: stateless.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     (new DateTimeType())->convertToDatabaseValue(new \DateTimeImmutable('2026-11-27 10:11:12'), $platform);   // '2026-11-27 10:11:12'
 *
 * References: ADR-007; SPEC-001 §2.4.
 *
 * @see https://www.php.net/manual/en/datetime.format.php
 */
final readonly class DateTimeType implements TypeConverter
{
    private const FORMAT = 'Y-m-d H:i:s';

    #[\Override]
    public function convertToDatabaseValue(mixed $value, Platform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(self::FORMAT);
        }

        throw TypeConversionException::conversionFailed(get_debug_type($value), 'datetime');
    }

    #[\Override]
    public function convertToPhpValue(mixed $value, Platform $platform): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $dt = \DateTimeImmutable::createFromFormat(self::FORMAT, ScalarValue::asString($value, 'datetime'));

        if ($dt === false) {
            $dt = new \DateTimeImmutable(ScalarValue::asString($value, 'datetime'));
        }

        return $dt;
    }

    /**
     * @param array<string, mixed> $column
     */
    #[\Override]
    public function getSqlDeclaration(array $column, Platform $platform): string
    {
        if ($platform->version->isPostgreSql()) {
            return 'TIMESTAMP WITHOUT TIME ZONE';
        }

        if ($platform->version->isSqlite()) {
            return 'TEXT';
        }

        return 'DATETIME';
    }
}
