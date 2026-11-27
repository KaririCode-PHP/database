<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;

final readonly class SmallIntType implements TypeConverter
{
    #[\Override]
    public function convertToDatabaseValue(mixed $value, Platform $platform): ?int
    {
        return $value === null ? null : ScalarValue::asInt($value, 'smallint');
    }

    #[\Override]
    public function convertToPhpValue(mixed $value, Platform $platform): ?int
    {
        return $value === null ? null : ScalarValue::asInt($value, 'smallint');
    }

    /**
     * @param array<string, mixed> $column
     */
    #[\Override]
    public function getSqlDeclaration(array $column, Platform $platform): string
    {
        return 'SMALLINT';
    }
}
