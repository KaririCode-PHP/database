<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;
use KaririCode\Database\Exception\TypeConversionException;

final readonly class DateTimeTzType implements TypeConverter
{
    private const FORMAT = 'Y-m-d H:i:sP';

    #[\Override]
    public function convertToDatabaseValue(mixed $value, Platform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(self::FORMAT);
        }
        throw TypeConversionException::conversionFailed(get_debug_type($value), 'datetimetz');
    }

    #[\Override]
    public function convertToPhpValue(mixed $value, Platform $platform): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat(self::FORMAT, ScalarValue::asString($value, 'datetimetz'));
        return $dt ?: new \DateTimeImmutable(ScalarValue::asString($value, 'datetimetz'));
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
}
