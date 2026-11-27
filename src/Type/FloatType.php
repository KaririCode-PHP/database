<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;

final readonly class FloatType implements TypeConverter
{
    #[\Override]
    public function convertToDatabaseValue(mixed $value, Platform $platform): ?float
    {
        return $value === null ? null : ScalarValue::asFloat($value, 'float');
    }

    #[\Override]
    public function convertToPhpValue(mixed $value, Platform $platform): ?float
    {
        return $value === null ? null : ScalarValue::asFloat($value, 'float');
    }

    /**
     * @param array<string, mixed> $column
     */
    #[\Override]
    public function getSqlDeclaration(array $column, Platform $platform): string
    {
        $version = $platform->version;

        if ($version->isPostgreSql()) {
            return 'DOUBLE PRECISION';
        }

        return 'DOUBLE';
    }
}
