<?php

declare(strict_types=1);

namespace KaririCode\Database\Driver\ExceptionConverter;

use KaririCode\Database\Exception\DatabaseException;
use KaririCode\Database\Exception\QueryException;

/**
 * SQLite error conversion. pdo_sqlite reports almost everything as
 * SQLSTATE HY000, so the result code in errorInfo[1] and the constraint
 * text decide: SQLITE_BUSY (5) and SQLITE_LOCKED (6) are lock timeouts,
 * which the retry policy treats as transient, and "UNIQUE constraint
 * failed" and its siblings are the integrity violations. A syntax error
 * stays an execute failure (6039): SQLite gives it no code of its own.
 *
 * Preconditions: constraint texts are SQLite's own, which are not
 * translated.
 *
 * Thread-safety: stateless.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $converter = new SqliteExceptionConverter();
 *     $converter->convert($e);   // "UNIQUE constraint failed: users.email" => QueryException 6032
 *
 * References: ADR-004; SPEC-001 §3.1.
 *
 * @see https://www.sqlite.org/rescode.html
 */
final class SqliteExceptionConverter extends AbstractExceptionConverter
{
    #[\Override]
    protected function convertFromVendorCode(\Throwable $exception, ?string $sql): ?DatabaseException
    {
        $resultCode = $exception instanceof \PDOException && isset($exception->errorInfo[1]) && is_int($exception->errorInfo[1]) ? $exception->errorInfo[1] : 0;
        $message = $exception->getMessage();

        return match (true) {
            $resultCode === 5, $resultCode === 6 => QueryException::lockTimeout($exception),
            str_contains($message, 'UNIQUE constraint failed') => QueryException::uniqueViolation($exception),
            str_contains($message, 'FOREIGN KEY constraint failed') => QueryException::foreignKeyViolation($exception),
            str_contains($message, 'NOT NULL constraint failed') => QueryException::notNullViolation($exception),
            default => null,
        };
    }
}
