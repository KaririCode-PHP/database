<?php

declare(strict_types=1);

namespace KaririCode\Database\Exception;

use KaririCode\Contract\Exception\Runtime;

// ── TypeConversionException ──────────────────────────────────────────

/**
 * A value could not be converted between its PHP and database types.
 *
 * Preconditions: none; instances come only from the named factories
 *   below (the constructor is private).
 * Side effects: none.
 * Thread-safety: immutable after construction.
 *
 * Family: Contract\Exception\Runtime (Spec V4.3.3 §8.4). Messages carry
 * the [DB_nnnn] code and never echo SQL, driver text, DSNs or values
 * (E-047); the driver error stays on getPrevious().
 *
 * Example:
 *   throw TypeConversionException::conversionFailed(get_debug_type($value), 'datetime');
 *
 * @see https://www.rfc-editor.org/rfc/rfc9110 (status mapping at the edge)
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
