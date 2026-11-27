<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;
use KaririCode\Database\Exception\TypeConversionException;

final readonly class DateIntervalType implements TypeConverter
{
    #[\Override]
    public function convertToDatabaseValue(mixed $value, Platform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \DateInterval) {
            return 'P' . $value->y . 'Y' . $value->m . 'M' . $value->d . 'DT'
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
        return new \DateInterval(ScalarValue::asString($value, 'dateinterval'));
    }

    /**
     * @param array<string, mixed> $column
     */
    #[\Override]
    public function getSqlDeclaration(array $column, Platform $platform): string
    {
        if ($platform->version->isPostgreSql()) {
            return 'INTERVAL';
        }
        return 'VARCHAR(255)';
    }
}
