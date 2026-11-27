<?php

declare(strict_types=1);

namespace KaririCode\Database\Driver\ExceptionConverter;

use KaririCode\Database\Exception\ConfigurationException;
use KaririCode\Database\Exception\ConnectionException;
use KaririCode\Database\Exception\DatabaseException;
use KaririCode\Database\Exception\QueryException;

final class MySqlExceptionConverter extends AbstractExceptionConverter
{
    #[\Override]
    protected function convertFromVendorCode(\Throwable $exception, ?string $sql): ?DatabaseException
    {
        $vendorCode = $exception instanceof \PDOException && isset($exception->errorInfo[1]) && is_int($exception->errorInfo[1]) ? $exception->errorInfo[1] : 0;
        $factory = $this->factories()[$vendorCode] ?? null;

        return $factory === null ? null : $factory($exception);
    }

    /**
     * Codigo de erro do MySQL => fabrica. Tabela em vez de match: cada codigo
     * novo e uma linha, sem somar ramos a complexidade (Spec V4.3.3 §13).
     *
     * @return array<int, \Closure(\Throwable): DatabaseException>
     */
    private function factories(): array
    {
        return [
            1045 => ConfigurationException::authenticationFailed(...),
            1049 => ConfigurationException::databaseNotFound(...),
            1048 => QueryException::notNullViolation(...),
            1062 => QueryException::uniqueViolation(...),
            1205 => QueryException::lockTimeout(...),
            1213 => QueryException::deadlock(...),
            1451 => QueryException::foreignKeyViolation(...),
            1452 => QueryException::foreignKeyViolation(...),
            2002 => ConnectionException::connectionFailed(...),
            2006 => ConnectionException::connectionLost(...),
        ];
    }
}
