<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;
use KaririCode\Database\Exception\TypeConversionException;

final readonly class JsonType implements TypeConverter
{
    #[\Override]
    public function convertToDatabaseValue(mixed $value, Platform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return $json;
    }

    #[\Override]
    public function convertToPhpValue(mixed $value, Platform $platform): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return json_decode(ScalarValue::asString($value, 'json'), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $column
     */
    #[\Override]
    public function getSqlDeclaration(array $column, Platform $platform): string
    {
        $version = $platform->version;

        if ($version->isPostgreSql()) {
            return 'JSONB';
        }

        if ($version->isSqlite()) {
            return 'TEXT';
        }

        return 'JSON';
    }
}
