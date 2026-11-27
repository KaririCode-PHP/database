<?php

declare(strict_types=1);

namespace KaririCode\Database\Driver\ExceptionConverter;

use KaririCode\Database\Exception\ConfigurationException;
use KaririCode\Database\Contract\ExceptionConverter;
use KaririCode\Database\Exception\ConnectionException;
use KaririCode\Database\Exception\DatabaseException;
use KaririCode\Database\Exception\QueryException;

// ── AbstractExceptionConverter ───────────────────────────────────────
//
// Shared SQLSTATE-based conversion. Per-driver converters extend
// with vendor-specific error codes.
//
// Ref: ISO/IEC 9075-2:2023 — SQLSTATE classes:
//   08 = connection, 23 = integrity, 28 = auth, 40 = tx rollback, 42 = syntax
// ─────────────────────────────────────────────────────────────────────

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

    abstract protected function convertFromVendorCode(\Throwable $exception, ?string $sql): ?DatabaseException;
}
