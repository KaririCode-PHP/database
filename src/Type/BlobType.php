<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;

final readonly class BlobType implements TypeConverter
{
    #[\Override]
    public function convertToDatabaseValue(mixed $value, Platform $platform): mixed
    {
        return $value;
    }

    #[\Override]
    public function convertToPhpValue(mixed $value, Platform $platform): mixed
    {
        if (is_resource($value)) {
            return stream_get_contents($value);
        }
        return $value;
    }

    /**
     * @param array<string, mixed> $column
     */
    #[\Override]
    public function getSqlDeclaration(array $column, Platform $platform): string
    {
        if ($platform->version->isPostgreSql()) {
            return 'BYTEA';
        }
        if ($platform->version->isMySQL() || $platform->version->isMariaDb()) {
            return 'LONGBLOB';
        }
        return 'BLOB';
    }
}
