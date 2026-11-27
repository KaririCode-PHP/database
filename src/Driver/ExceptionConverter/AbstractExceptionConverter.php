<?php

declare(strict_types=1);

namespace KaririCode\Database\Driver\ExceptionConverter;

use KaririCode\Database\Contract\ExceptionConverter;
use KaririCode\Database\Exception\ConfigurationException;
use KaririCode\Database\Exception\ConnectionException;
use KaririCode\Database\Exception\DatabaseException;
use KaririCode\Database\Exception\QueryException;

/**
 * Turns a driver error into the package exception a caller can act on:
 * retry a deadlock, report a unique violation, fix the credentials. The
 * vendor code is tried first, because MySQL reports unique, foreign-key
 * and not-null violations all under the generic SQLSTATE 23000; the
 * SQLSTATE class (08 connection, 23 integrity, 28 authorization,
 * 40 rollback, 42 syntax or access rule) comes next, and anything else is
 * an execute failure (6039).
 *
 * Preconditions: none; any \Throwable is accepted and a non-PDO error is
 * an execute failure. The SQL text is accepted for subclasses and never
 * reaches a message (E-047).
 *
 * Thread-safety: stateless.
 *
 * Side effects: none; the driver error stays on getPrevious().
 *
 * Example:
 *
 *     try {
 *         $statement->execute();
 *     } catch (\PDOException $e) {
 *         throw $converter->convert($e);   // e.g. QueryException 6032
 *     }
 *
 * References: ADR-004 (semantic exception conversion); SPEC-001 §3.1 and
 * §12 (codes).
 *
 * @see https://www.postgresql.org/docs/current/errcodes-appendix.html
 */
abstract class AbstractExceptionConverter implements ExceptionConverter
{
    #[\Override]
    public function convert(\Throwable $exception, ?string $sql = null): DatabaseException
    {
        if ($exception instanceof \PDOException) {
            $sqlState = $exception->getCode();

            // Codigo do fabricante primeiro: no MySQL o SQLSTATE 23000 e generico
            // e esconderia o 1062 (unique) atras de "constraint violation".
            return $this->convertFromVendorCode($exception, $sql)
                ?? $this->convertFromSqlState(sqlState: (string) $sqlState, previous: $exception, sql: $sql)
                ?? QueryException::executeFailed($exception);
        }

        return QueryException::executeFailed($exception);
    }

    /**
     * Converts an error raised while opening the connection. Only
     * configuration and connection outcomes make sense there: a lost
     * connection is a failed one, and an error with no connection meaning
     * (a SQLite file that cannot be opened is SQLSTATE HY000) becomes
     * connectionFailed (6010), so RetryMiddleware sees one family.
     */
    public function convertConnectFailure(\Throwable $exception): DatabaseException
    {
        $converted = $this->convertFromConnectMessage($exception) ?? $this->convert($exception);

        if ($converted instanceof ConfigurationException) {
            return $converted;
        }

        return $converted instanceof ConnectionException && $converted->getCode() !== 6011
            ? $converted
            : ConnectionException::connectionFailed($exception);
    }

    protected function convertFromSqlState(string $sqlState, \Throwable $previous, ?string $sql): ?DatabaseException
    {
        $class = substr($sqlState, 0, 2);

        return match ($class) {
            '08' => ConnectionException::connectionFailed($previous),
            '28' => ConfigurationException::authenticationFailed($previous),
            '23' => $this->convertIntegrityViolation($sqlState, $previous),
            '40' => QueryException::deadlock($previous),
            '42' => QueryException::syntaxError($previous),
            default => null,
        };
    }

    protected function convertIntegrityViolation(string $sqlState, \Throwable $previous): DatabaseException
    {
        return match ($sqlState) {
            '23505' => QueryException::uniqueViolation($previous),
            '23503' => QueryException::foreignKeyViolation($previous),
            '23502' => QueryException::notNullViolation($previous),
            default => QueryException::constraintViolation($previous),
        };
    }

    /**
     * Drivers whose connect errors carry no specific code (libpq reports
     * every refused connection as SQLSTATE 08006) read the message here.
     */
    protected function convertFromConnectMessage(\Throwable $exception): ?DatabaseException
    {
        return null;
    }

    abstract protected function convertFromVendorCode(\Throwable $exception, ?string $sql): ?DatabaseException;
}
