<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;

final readonly class BooleanType implements TypeConverter
{
    #[\Override]
    public function convertToDatabaseValue(mixed $value, Platform $platform): ?int
    {
        if ($value === null) {
            return null;
        }

        return $value ? 1 : 0;
    }

    #[\Override]
    public function convertToPhpValue(mixed $value, Platform $platform): ?bool
    {
        if ($value === null) {
            return null;
        }

        return (bool) $value;
    }

    /**
     * @param array<string, mixed> $column
     */
    #[\Override]
    public function getSqlDeclaration(array $column, Platform $platform): string
    {
        if ($platform->version->isPostgreSql()) {
            return 'BOOLEAN';
        }

        return 'TINYINT(1)';
    }
}
