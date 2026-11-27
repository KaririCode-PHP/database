<?php

declare(strict_types=1);

namespace KaririCode\Database\Exception;

use KaririCode\Contract\Exception\Runtime;

/**
 * A value could not be converted between its PHP and database types: a
 * fractional float read as an integer, text that is no number, an object
 * a type does not accept.
 *
 * Preconditions: none; instances come only from the named factories (the
 * constructor is private).
 *
 * Thread-safety: immutable after construction.
 *
 * Side effects: none.
 *
 * Family: Contract\Exception\Runtime. The message names the two types,
 * never the value (E-047).
 *
 * Example:
 *
 *     throw TypeConversionException::conversionFailed(get_debug_type($value), 'datetime');
 *
 * References: Spec V4.3.3 §8.4; ADR-007; SPEC-001 §12 (code 6120).
 *
 * @see https://www.php.net/manual/en/language.types.type-juggling.php
 * @since 4.3.0
 */
final class TypeConversionException extends \RuntimeException implements DatabaseException, Runtime
{
    private function __construct(string $message, int $code, ?\Throwable $previous = null)
    {
        parent::__construct(message: $message, code: $code, previous: $previous);
    }

    /** $phpType comes from get_debug_type() and $dbType is a converter name: type names, never the value. */
    public static function conversionFailed(string $phpType, string $dbType): self
    {
        return new self("[DB_6120] Cannot convert a {$phpType} value to the {$dbType} type.", 6120);
    }
}
