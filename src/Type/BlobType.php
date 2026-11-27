<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;

/**
 * Stores large byte strings: BYTEA on PostgreSQL, LONGBLOB on MySQL, BLOB
 * on SQLite. A stream returned by the driver (PostgreSQL) is read back
 * into a string.
 *
 * Preconditions: bind the value as ParameterType::BINARY; the whole value
 * is held in memory.
 *
 * Thread-safety: stateless.
 *
 * Side effects: reads the stream the driver returned.
 *
 * Example:
 *
 *     $pdf = (new BlobType())->convertToPhpValue($row['document'], $platform);
 *
 * References: ADR-007; SPEC-001 §2.4.
 *
 * @see https://dev.mysql.com/doc/refman/8.4/en/blob.html
 */
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
