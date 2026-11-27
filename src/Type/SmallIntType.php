<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\TypeConverter;

/**
 * Stores small integers as SMALLINT and reads them as PHP int, with the
 * same refusals as IntegerType.
 *
 * Preconditions: the value fits the column (-32768 to 32767 on the
 * servers); the server, not this type, rejects one that does not.
 *
 * Thread-safety: stateless.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     (new SmallIntType())->getSqlDeclaration([], $platform);   // SMALLINT
 *
 * References: ADR-007; SPEC-001 §2.4.
 *
 * @see https://www.postgresql.org/docs/current/datatype-numeric.html#DATATYPE-INT
 */
final readonly class SmallIntType implements TypeConverter
{
    #[\Override]
    public function convertToDatabaseValue(mixed $value, Platform $platform): ?int
    {
        return $value === null ? null : ScalarValue::asInt($value, 'smallint');
    }

    #[\Override]
    public function convertToPhpValue(mixed $value, Platform $platform): ?int
    {
        return $value === null ? null : ScalarValue::asInt($value, 'smallint');
    }

    /**
     * @param array<string, mixed> $column
     */
    #[\Override]
    public function getSqlDeclaration(array $column, Platform $platform): string
    {
        return 'SMALLINT';
    }
}
