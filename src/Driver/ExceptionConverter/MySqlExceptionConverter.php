<?php

declare(strict_types=1);

namespace KaririCode\Database\Driver\ExceptionConverter;

use KaririCode\Database\Exception\ConfigurationException;
use KaririCode\Database\Exception\ConnectionException;
use KaririCode\Database\Exception\DatabaseException;
use KaririCode\Database\Exception\QueryException;

/**
 * MySQL and MariaDB error conversion. Both servers report integrity
 * violations under SQLSTATE 23000 and most connect errors under HY000, so
 * the vendor code in errorInfo[1] decides: 1045 and 1044 are credentials,
 * 1049 an unknown database, 1062 a duplicate key, 1213 a deadlock, 2002 a
 * refused connection.
 *
 * Preconditions: the PDOException carries errorInfo, which pdo_mysql
 * fills for connect and statement errors alike; without it the SQLSTATE
 * class decides.
 *
 * Thread-safety: stateless.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $converter = new MySqlExceptionConverter();
 *     $converter->convert($pdoException);   // errorInfo[1] 1062 => QueryException 6032
 *
 * References: ADR-004; SPEC-001 §3.1.
 *
 * @see https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html
 */
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
            1040 => ConnectionException::tooManyConnections(...),
            1044 => ConfigurationException::authenticationFailed(...),
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
            2013 => ConnectionException::connectionLost(...),
        ];
    }
}
