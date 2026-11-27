<?php

declare(strict_types=1);

namespace KaririCode\Database\Contract;

interface TypeConverter
{
    public function convertToDatabaseValue(mixed $value, Platform $platform): mixed;

    public function convertToPhpValue(mixed $value, Platform $platform): mixed;

    /**
     * @param array<string, mixed> $column
     */
    public function getSqlDeclaration(array $column, Platform $platform): string;
}
