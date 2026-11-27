<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;

/**
 * Stores text up to a length as VARCHAR(length) on the servers and TEXT on
 * SQLite, which has no length limit.
 *
 * Preconditions: the value is a string or a scalar that converts to one;
 * the length defaults to 255 and is checked by the server, not here.
 *
 * Thread-safety: stateless.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     (new StringType())->getSqlDeclaration(['length' => 120], $platform);   // VARCHAR(120)
 *
 * References: ADR-007; SPEC-001 §2.4.
 *
 * @see https://www.postgresql.org/docs/current/datatype-character.html
 */
final readonly class StringType implements TypeConverter
{
    #[\Override]
    public function convertToDatabaseValue(mixed $value, Platform $platform): ?string
    {
        return $value === null ? null : ScalarValue::asString($value, 'string');
    }

    #[\Override]
    public function convertToPhpValue(mixed $value, Platform $platform): ?string
    {
        return $value === null ? null : ScalarValue::asString($value, 'string');
    }

    /**
     * @param array<string, mixed> $column
     */
    #[\Override]
    public function getSqlDeclaration(array $column, Platform $platform): string
    {
        $length = ScalarValue::asInt($column['length'] ?? 255, 'string');

        if ($platform->version->isSqlite()) {
            return 'TEXT';
        }

        return "VARCHAR({$length})";
    }
}
