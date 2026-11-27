<?php

declare(strict_types=1);

namespace KaririCode\Database\Exception;

use KaririCode\Contract\Exception\Runtime;

// ── ConnectionException ──────────────────────────────────────────────

/**
 * The database server could not be reached or dropped the connection;
 * usually transient, so a retry policy may try again.
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
 *   throw ConnectionException::connectionLost($pdoException);
 *
 * @see https://www.rfc-editor.org/rfc/rfc9110 (status mapping at the edge)
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
