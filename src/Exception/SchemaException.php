<?php

declare(strict_types=1);

namespace KaririCode\Database\Exception;

use KaririCode\Contract\Exception\NotFound;

/**
 * A schema object the caller asked for does not exist: a table or a
 * column.
 *
 * Preconditions: none; instances come only from the named factories (the
 * constructor is private).
 *
 * Thread-safety: immutable after construction.
 *
 * Side effects: none.
 *
 * Family: Contract\Exception\NotFound. Messages carry the [DB_nnnn] code
 * and never echo the requested name (E-047).
 *
 * Example:
 *
 *     throw SchemaException::tableNotFound();
 *
 * References: Spec V4.3.3 §8.4; ADR-008; SPEC-001 §12 (codes 6100, 6101).
 *
 * @see https://www.rfc-editor.org/rfc/rfc9110
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
