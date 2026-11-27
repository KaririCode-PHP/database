<?php

declare(strict_types=1);

namespace KaririCode\Database\Driver\ExceptionConverter;

use KaririCode\Database\Exception\DatabaseException;
use KaririCode\Database\Exception\QueryException;

final class SqliteExceptionConverter extends AbstractExceptionConverter
{
    #[\Override]
    protected function convertFromVendorCode(\Throwable $exception, ?string $sql): ?DatabaseException
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'UNIQUE constraint failed')) {
            return QueryException::uniqueViolation($exception);
        }

        if (str_contains($message, 'FOREIGN KEY constraint failed')) {
            return QueryException::foreignKeyViolation($exception);
        }

        if (str_contains($message, 'NOT NULL constraint failed')) {
            $column = $this->extractColumnName($message);
            return QueryException::notNullViolation($exception);
        }

        return null;
    }

    private function extractColumnName(string $message): string
    {
        if (preg_match('/NOT NULL constraint failed: (\S+)/', $message, $matches)) {
            return $matches[1];
        }

        return 'unknown';
    }
}
