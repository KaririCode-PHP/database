<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;

/**
 * Stores UUIDs as text: the native UUID type on PostgreSQL, CHAR(36)
 * elsewhere.
 *
 * Preconditions: the value is the canonical 36-character form; the type
 * does not validate it, PostgreSQL does.
 *
 * Thread-safety: stateless.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     (new UuidType())->getSqlDeclaration([], $mysqlPlatform);   // CHAR(36)
 *
 * References: ADR-007; SPEC-001 §2.4.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9562
 */
final readonly class UuidType implements TypeConverter
{
    #[\Override]
    public function convertToDatabaseValue(mixed $value, Platform $platform): ?string
    {
        return $value === null ? null : ScalarValue::asString($value, 'uuid');
    }

    #[\Override]
    public function convertToPhpValue(mixed $value, Platform $platform): ?string
    {
        return $value === null ? null : ScalarValue::asString($value, 'uuid');
    }

    /**
     * @param array<string, mixed> $column
     */
    #[\Override]
    public function getSqlDeclaration(array $column, Platform $platform): string
    {
        if ($platform->version->isPostgreSql()) {
            return 'UUID';
        }

        return 'CHAR(36)';
    }
}
