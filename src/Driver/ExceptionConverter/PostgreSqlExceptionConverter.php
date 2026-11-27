<?php

declare(strict_types=1);

namespace KaririCode\Database\Driver\ExceptionConverter;

use KaririCode\Database\Exception\ConfigurationException;
use KaririCode\Database\Exception\ConnectionException;
use KaririCode\Database\Exception\DatabaseException;

/**
 * PostgreSQL error conversion. Statement errors carry the full SQLSTATE
 * (23505 unique, 23503 foreign key, 40P01 deadlock, 42P01 unknown table).
 * libpq reports every refused connection as 08006, so at connect time the
 * server message decides between wrong credentials, an unknown database
 * and a full server.
 *
 * Preconditions: the connect messages are matched in English, the
 * server's default lc_messages; with translated messages a refused
 * connection is connectionFailed (6010), never a wrong family.
 *
 * Thread-safety: stateless.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $converter = new PostgreSqlExceptionConverter();
 *     $converter->convertConnectFailure($e);   // "password authentication failed" => 6012
 *
 * References: ADR-004; SPEC-001 §3.1.
 *
 * @see https://www.postgresql.org/docs/current/errcodes-appendix.html
 */
final class PostgreSqlExceptionConverter extends AbstractExceptionConverter
{
    /**
     * Only 08006 needs a rule of its own: after the connect it is a lost
     * connection, which the SQLSTATE class 08 alone would call a failed
     * one. The full SQLSTATEs PostgreSQL reports (23505, 40P01, 28P01,
     * 42P01) fall in the classes the base converter maps.
     */
    #[\Override]
    protected function convertFromVendorCode(\Throwable $exception, ?string $sql): ?DatabaseException
    {
        return $exception instanceof \PDOException && $exception->getCode() === '08006'
            ? ConnectionException::connectionLost($exception)
            : null;
    }

    #[\Override]
    protected function convertFromConnectMessage(\Throwable $exception): ?DatabaseException
    {
        $message = $exception->getMessage();

        return match (true) {
            str_contains($message, 'password authentication failed') => ConfigurationException::authenticationFailed($exception),
            preg_match('/database ".*" does not exist/', $message) === 1 => ConfigurationException::databaseNotFound($exception),
            str_contains($message, 'too many clients') => ConnectionException::tooManyConnections($exception),
            default => null,
        };
    }
}
