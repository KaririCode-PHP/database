<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;
use KaririCode\Database\Exception\TypeConversionException;

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
