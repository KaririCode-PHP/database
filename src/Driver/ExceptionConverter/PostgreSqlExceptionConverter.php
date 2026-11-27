<?php

declare(strict_types=1);

namespace KaririCode\Database\Driver\ExceptionConverter;

use KaririCode\Database\Exception\ConfigurationException;
use KaririCode\Database\Exception\ConnectionException;
use KaririCode\Database\Exception\DatabaseException;
use KaririCode\Database\Exception\QueryException;

final class PostgreSqlExceptionConverter extends AbstractExceptionConverter
{
    #[\Override]
    protected function convertFromVendorCode(\Throwable $exception, ?string $sql): ?DatabaseException
    {
        if (!$exception instanceof \PDOException) {
            return null;
        }

        $sqlState = (string) ($exception->getCode());

        return match ($sqlState) {
            '23505' => QueryException::uniqueViolation($exception),
            '23503' => QueryException::foreignKeyViolation($exception),
            '23502' => QueryException::notNullViolation($exception),
            '40P01' => QueryException::deadlock($exception),
            '42P01' => QueryException::syntaxError($exception),
            '08006' => ConnectionException::connectionLost($exception),
            '28P01' => ConfigurationException::authenticationFailed($exception),
            default => null,
        };
    }
}
