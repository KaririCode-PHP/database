<?php

declare(strict_types=1);

namespace KaririCode\Database\Exception;

use KaririCode\Contract\Exception\NotFound;

// ── SchemaException ──────────────────────────────────────────────────

/**
 * A schema object the caller asked for does not exist.
 *
 * Preconditions: none; instances come only from the named factories
 *   below (the constructor is private).
 * Side effects: none.
 * Thread-safety: immutable after construction.
 *
 * Family: Contract\Exception\NotFound (Spec V4.3.3 §8.4). Messages carry
 * the [DB_nnnn] code and never echo SQL, driver text, DSNs or values
 * (E-047); the driver error stays on getPrevious().
 *
 * Example:
 *   throw SchemaException::tableNotFound();
 *
 * @see https://www.rfc-editor.org/rfc/rfc9110 (status mapping at the edge)
 * @since 4.3.0
 */
final class SchemaException extends \RuntimeException implements DatabaseException, NotFound
{
    private function __construct(string $message, int $code, ?\Throwable $previous = null)
    {
        parent::__construct(message: $message, code: $code, previous: $previous);
    }

    public static function tableNotFound(): self
    {
        return new self(message: '[DB_6100] The requested table does not exist.', code: 6100, previous: null);
    }

    public static function columnNotFound(): self
    {
        return new self(message: '[DB_6101] The requested column does not exist.', code: 6101, previous: null);
    }
}
