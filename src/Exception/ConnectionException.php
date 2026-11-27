<?php

declare(strict_types=1);

namespace KaririCode\Database\Exception;

use KaririCode\Contract\Exception\Runtime;

/**
 * The database server could not be reached, refused the connection as
 * full, timed out or dropped the session; usually transient, so a retry
 * policy may try again.
 *
 * Preconditions: none; instances come only from the named factories (the
 * constructor is private).
 *
 * Thread-safety: immutable after construction.
 *
 * Side effects: none.
 *
 * Family: Contract\Exception\Runtime. Messages carry the [DB_nnnn] code
 * and never echo hosts, DSNs or driver text (E-047); the driver error
 * stays on getPrevious().
 *
 * Example:
 *
 *     throw ConnectionException::connectionLost($pdoException);
 *
 * References: Spec V4.3.3 §8.4; ADR-004; SPEC-001 §12 (codes 6010, 6011,
 * 6014, 6015).
 *
 * @see https://www.rfc-editor.org/rfc/rfc9110
 * @since 4.3.0
 */
final class ConnectionException extends \RuntimeException implements DatabaseException, Runtime
{
    private function __construct(string $message, int $code, ?\Throwable $previous = null)
    {
        parent::__construct(message: $message, code: $code, previous: $previous);
    }

    public static function connectionFailed(?\Throwable $previous = null): self
    {
        return new self(message: '[DB_6010] Could not connect to the database server.', code: 6010, previous: $previous);
    }

    public static function connectionLost(?\Throwable $previous = null): self
    {
        return new self(message: '[DB_6011] The connection to the database server was lost.', code: 6011, previous: $previous);
    }

    public static function connectionTimeout(?\Throwable $previous = null): self
    {
        return new self(message: '[DB_6014] Connecting to the database server timed out.', code: 6014, previous: $previous);
    }

    public static function tooManyConnections(?\Throwable $previous = null): self
    {
        return new self(message: '[DB_6015] The database server refused the connection: too many connections.', code: 6015, previous: $previous);
    }
}
