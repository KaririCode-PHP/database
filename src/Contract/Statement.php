<?php

declare(strict_types=1);

namespace KaririCode\Database\Contract;

/**
 * A prepared statement: bind values with their ParameterType, execute,
 * read the Result.
 *
 * Preconditions: positional parameters are 1-based; the type is a
 * ParameterType value, STRING by default.
 *
 * Thread-safety: it belongs to its session; not safe to share.
 *
 * Side effects: execute() runs the statement.
 *
 * Example:
 *
 *     $statement = $session->prepare('DELETE FROM sessions WHERE expires_at < ?');
 *     $statement->bindValue(1, $now->format('Y-m-d H:i:s'));
 *     $removed = $statement->execute()->rowCount();
 *
 * References: ADR-001; SPEC-001 §2.1.
 *
 * @see https://www.php.net/manual/en/pdostatement.bindvalue.php
 */
interface Statement
{
    public function bindValue(int|string $param, mixed $value, int $type = 2): void;

    /**
     * @param array<int|string, mixed> $params
     */
    public function execute(?array $params = null): Result;
}
