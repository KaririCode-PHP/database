<?php

declare(strict_types=1);

namespace KaririCode\Database\Driver\Pdo;

use KaririCode\Database\Contract\DriverConnection;
use KaririCode\Database\Contract\Statement;
use KaririCode\Database\Exception\QueryException;

// ── PdoConnection ────────────────────────────────────────────────────
//
// Thin wrapper over a PDO instance. Created by PdoMySqlDriver,
// PdoPgSqlDriver, or PdoSqliteDriver. The Connection layer
// wraps this with rich functionality.
// ─────────────────────────────────────────────────────────────────────

final class PdoConnection implements DriverConnection
{
    public function __construct(
        private readonly \PDO $pdo,
    ) {}

    #[\Override]
    public function prepare(string $sql): Statement
    {
        $stmt = $this->pdo->prepare($sql);

        if ($stmt === false) {
            throw QueryException::prepareFailed();
        }

        return new PdoStatement($stmt);
    }

    #[\Override]
    public function exec(string $sql): int
    {
        $result = $this->pdo->exec($sql);

        if ($result === false) {
            throw QueryException::executeFailed();
        }

        return $result;
    }

    #[\Override]
    public function lastInsertId(?string $name = null): string
    {
        return self::insertId($this->pdo->lastInsertId($name));
    }

    #[\Override]
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    #[\Override]
    public function commit(): void
    {
        $this->pdo->commit();
    }

    #[\Override]
    public function rollBack(): void
    {
        $this->pdo->rollBack();
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
