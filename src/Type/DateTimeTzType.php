<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;
use KaririCode\Database\Exception\TypeConversionException;

/**
 * Stores an instant with its time zone where the platform can:
 * TIMESTAMP WITH TIME ZONE on PostgreSQL, text with the offset on SQLite.
 * MySQL and MariaDB DATETIME keeps no offset (MariaDB rejects one), so
 * the instant is written in UTC there and read back in UTC: the moment
 * survives, the original offset does not.
 *
 * Preconditions: the PHP value is a \DateTimeInterface or null.
 *
 * Thread-safety: stateless.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $type = new DateTimeTzType();
 *     $type->convertToDatabaseValue(new \DateTimeImmutable('2026-11-27 10:00:00+02:00'), $mysqlPlatform);   // '2026-11-27 08:00:00'
 *
 * References: ADR-007 (type registry); SPEC-001 §2.4.
 *
 * @see https://dev.mysql.com/doc/refman/8.4/en/datetime.html
 */
final readonly class DateTimeTzType implements TypeConverter
{
    private const FORMAT = 'Y-m-d H:i:sP';
    private const UTC_FORMAT = 'Y-m-d H:i:s';

    #[\Override]
    public function convertToDatabaseValue(mixed $value, Platform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof \DateTimeInterface) {
            throw TypeConversionException::conversionFailed(get_debug_type($value), 'datetimetz');
        }

        return self::keepsNoOffset($platform)
            ? \DateTimeImmutable::createFromInterface($value)->setTimezone(new \DateTimeZone('UTC'))->format(self::UTC_FORMAT)
            : $value->format(self::FORMAT);
    }

    #[\Override]
    public function convertToPhpValue(mixed $value, Platform $platform): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = ScalarValue::asString($value, 'datetimetz');
        $dt = self::keepsNoOffset($platform)
            ? \DateTimeImmutable::createFromFormat(format: self::UTC_FORMAT, datetime: $text, timezone: new \DateTimeZone('UTC'))
            : \DateTimeImmutable::createFromFormat(self::FORMAT, $text);

        return $dt ?: new \DateTimeImmutable($text);
    }

    /**
     * @param array<string, mixed> $column
     */
    #[\Override]
    public function getSqlDeclaration(array $column, Platform $platform): string
    {
        if ($platform->version->isPostgreSql()) {
            return 'TIMESTAMP WITH TIME ZONE';
        }

        if ($platform->version->isSqlite()) {
            return 'TEXT';
        }

        return 'DATETIME';
    }

    private static function keepsNoOffset(Platform $platform): bool
    {
        return $platform->version->isMySQL() || $platform->version->isMariaDb();
    }
}
