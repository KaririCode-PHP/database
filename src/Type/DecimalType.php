<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;

final readonly class DecimalType implements TypeConverter
{
    #[\Override]
    public function convertToDatabaseValue(mixed $value, Platform $platform): ?string
    {
        return $value === null ? null : ScalarValue::asString($value, 'decimal');
    }

    #[\Override]
    public function convertToPhpValue(mixed $value, Platform $platform): ?string
    {
        return $value === null ? null : ScalarValue::asString($value, 'decimal');
    }

    /**
     * @param array<string, mixed> $column
     */
    #[\Override]
    public function getSqlDeclaration(array $column, Platform $platform): string
    {
        $precision = ScalarValue::asInt($column['precision'] ?? 10, 'decimal');
        $scale = ScalarValue::asInt($column['scale'] ?? 2, 'decimal');

        $typeName = $platform->version->isPostgreSql() ? 'NUMERIC' : 'DECIMAL';

        return "{$typeName}({$precision}, {$scale})";
    }
}
