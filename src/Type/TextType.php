<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;

/**
 * Stores long text: LONGTEXT on MySQL and MariaDB, TEXT elsewhere.
 *
 * Preconditions: the value is a string or a scalar that converts to one.
 *
 * Thread-safety: stateless.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     (new TextType())->getSqlDeclaration([], $mysqlPlatform);   // LONGTEXT
 *
 * References: ADR-007; SPEC-001 §2.4.
 *
 * @see https://dev.mysql.com/doc/refman/8.4/en/blob.html
 */
final readonly class TextType implements TypeConverter
{
    #[\Override]
    public function convertToDatabaseValue(mixed $value, Platform $platform): ?string
    {
        return $value === null ? null : ScalarValue::asString($value, 'text');
    }

    #[\Override]
    public function convertToPhpValue(mixed $value, Platform $platform): ?string
    {
        return $value === null ? null : ScalarValue::asString($value, 'text');
    }

    /**
     * @param array<string, mixed> $column
     */
    #[\Override]
    public function getSqlDeclaration(array $column, Platform $platform): string
    {
        if ($platform->version->isMySQL() || $platform->version->isMariaDb()) {
            return 'LONGTEXT';
        }

        return 'TEXT';
    }
}
