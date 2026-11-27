<?php

declare(strict_types=1);

namespace KaririCode\Database\Driver\Pdo;

use KaririCode\Database\Contract\DriverConnection;
use KaririCode\Database\Contract\ExceptionConverter;
use KaririCode\Database\Contract\Statement;
use KaririCode\Database\Exception\QueryException;

/**
 * The driver-level connection over one PDO handle: prepares, executes and
 * runs the native transaction calls, and is the boundary where a
 * PDOException becomes a package exception through the driver's
 * converter, so no layer above sees a raw driver error.
 *
 * Preconditions: the PDO handle is open and in ERRMODE_EXCEPTION, as the
 * three PDO drivers configure it; with another error mode a failed
 * prepare or exec still fails, as prepareFailed (6038) or executeFailed
 * (6039).
 *
 * Thread-safety: not safe to share; one PDO handle is one session.
 *
 * Side effects: every method talks to the database server (or the SQLite
 * file); beginTransaction(), commit() and rollBack() change the session's
 * transaction state.
 *
 * Example:
 *
 *     $connection = (new PdoSqliteDriver())->connect(new ConnectionParams(driver: 'pdo_sqlite'));
 *     $connection->exec('CREATE TABLE t (id INTEGER)');
 *     $connection->prepare('INSERT INTO t VALUES (?)')->execute([1]);
 *
 * References: ADR-001 (two-layer driver/connection); ADR-004 (conversion
 * at the driver boundary); SPEC-001 §3.
 *
 * @see https://www.php.net/manual/en/class.pdo.php
 */
final class PdoConnection implements DriverConnection
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly ExceptionConverter $converter,
    ) {}

    #[\Override]
    public function prepare(string $sql): Statement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
        } catch (\PDOException $e) {
            throw $this->converter->convert($e, $sql);
        }

        if ($stmt === false) {
            throw QueryException::prepareFailed();
        }

        return new PdoStatement($stmt, $this->converter);
    }

    #[\Override]
    public function exec(string $sql): int
    {
        try {
            $result = $this->pdo->exec($sql);
        } catch (\PDOException $e) {
            throw $this->converter->convert($e, $sql);
        }

        if ($result === false) {
            throw QueryException::executeFailed();
        }

        return $result;
    }

    #[\Override]
    public function lastInsertId(?string $name = null): string
    {
        try {
            return self::insertId($this->pdo->lastInsertId($name));
        } catch (\PDOException $e) {
            throw $this->converter->convert($e);
        }
    }

    #[\Override]
    public function beginTransaction(): void
    {
        try {
            $this->pdo->beginTransaction();
        } catch (\PDOException $e) {
            throw $this->converter->convert($e);
        }
    }

    #[\Override]
    public function commit(): void
    {
        try {
            $this->pdo->commit();
        } catch (\PDOException $e) {
            throw $this->converter->convert($e);
        }
    }

    #[\Override]
    public function rollBack(): void
    {
        try {
            $this->pdo->rollBack();
        } catch (\PDOException $e) {
            throw $this->converter->convert($e);
        }
    }

    #[\Override]
    public function nativeConnection(): \PDO
    {
        return $this->pdo;
    }

    /** PDO::lastInsertId() devolve false quando o driver nao informa o id. */
    private static function insertId(mixed $id): string
    {
        return is_string($id) ? $id : throw QueryException::executeFailed();
    }
}
