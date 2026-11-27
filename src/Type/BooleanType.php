<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;

/**
 * Stores booleans as BOOLEAN on PostgreSQL and TINYINT(1) elsewhere,
 * written as 1 or 0 and read back as bool.
 *
 * Preconditions: any PHP value is accepted and written by truthiness;
 * bind it as ParameterType::BOOLEAN for PostgreSQL.
 *
 * Thread-safety: stateless.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $connection->executeStatement('UPDATE users SET active = ? WHERE id = ?', [(new BooleanType())->convertToDatabaseValue(true, $platform), 7], [ParameterType::BOOLEAN->value]);
 *
 * References: ADR-007; SPEC-001 §2.4.
 *
 * @see https://www.postgresql.org/docs/current/datatype-boolean.html
 */
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
