<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;

/**
 * Stores short byte strings: BYTEA on PostgreSQL, BLOB on SQLite,
 * VARBINARY(length) on MySQL. A stream returned by the driver
 * (PostgreSQL) is read back into a string.
 *
 * Preconditions: bind the value as ParameterType::BINARY, or PostgreSQL
 * reads a NUL byte as the end of text; the length defaults to 255.
 *
 * Thread-safety: stateless.
 *
 * Side effects: reads the stream the driver returned.
 *
 * Example:
 *
 *     $connection->executeStatement('INSERT INTO keys (fingerprint) VALUES (?)', [$bytes], [ParameterType::BINARY->value]);
 *
 * References: ADR-007; SPEC-001 §2.4.
 *
 * @see https://www.postgresql.org/docs/current/datatype-binary.html
 */
final readonly class BinaryType implements TypeConverter
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
        $length = ScalarValue::asInt($column['length'] ?? 255, 'binary');
        if ($platform->version->isPostgreSql()) {
            return 'BYTEA';
        }
        if ($platform->version->isSqlite()) {
            return 'BLOB';
        }
        return "VARBINARY({$length})";
    }
}
