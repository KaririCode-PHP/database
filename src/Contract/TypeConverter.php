<?php

declare(strict_types=1);

namespace KaririCode\Database\Contract;

/**
 * Converts one logical type between PHP and the database for a platform
 * and writes its column declaration, so a datetime, a JSON document or a
 * UUID is stored and read back the same way on every vendor.
 *
 * Preconditions: convertToDatabaseValue() takes the PHP type the
 * converter names, or null; a value it cannot represent throws
 * TypeConversionException (6120).
 *
 * Thread-safety: implementations are stateless.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $json = (new TypeRegistry())->get('json');
 *     $connection->insert('events', ['payload' => $json->convertToDatabaseValue($payload, $connection->platform)]);
 *
 * References: ADR-007 (type registry); SPEC-001 §2.1.
 *
 * @see https://www.php.net/manual/en/language.types.type-juggling.php
 */
interface TypeConverter
{
    public function convertToDatabaseValue(mixed $value, Platform $platform): mixed;

    public function convertToPhpValue(mixed $value, Platform $platform): mixed;

    /**
     * @param array<string, mixed> $column
     */
    public function getSqlDeclaration(array $column, Platform $platform): string;
}
