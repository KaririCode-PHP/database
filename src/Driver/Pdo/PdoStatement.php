<?php

declare(strict_types=1);

namespace KaririCode\Database\Driver\Pdo;

use KaririCode\Database\Contract\ExceptionConverter;
use KaririCode\Database\Contract\Result;
use KaririCode\Database\Contract\Statement;
use KaririCode\Database\Enum\ParameterType;

/**
 * One prepared statement over PDOStatement: binds values with the PDO
 * type that matches each ParameterType and executes, converting driver
 * errors (a constraint violation surfaces here) into package exceptions.
 *
 * Preconditions: positional parameters are 1-based, as in PDO; a type
 * outside ParameterType binds as a string.
 *
 * Thread-safety: not safe to share; it belongs to its connection.
 *
 * Side effects: execute() runs the statement on the server.
 *
 * Example:
 *
 *     $statement = $connection->prepare('SELECT name FROM users WHERE id = ?');
 *     $statement->bindValue(1, 42, ParameterType::INTEGER->value);
 *     $name = $statement->execute()->fetchOne();
 *
 * References: ADR-001; ADR-004; SPEC-001 §3.
 *
 * @see https://www.php.net/manual/en/pdostatement.bindvalue.php
 */
final class PdoStatement implements Statement
{
    public function __construct(
        private readonly \PDOStatement $stmt,
        private readonly ExceptionConverter $converter,
    ) {}

    #[\Override]
    public function bindValue(int|string $param, mixed $value, int $type = 2): void
    {
        $pdoType = match ($type) {
            ParameterType::NULL->value => \PDO::PARAM_NULL,
            ParameterType::INTEGER->value => \PDO::PARAM_INT,
            ParameterType::STRING->value => \PDO::PARAM_STR,
            ParameterType::BINARY->value => \PDO::PARAM_LOB,
            ParameterType::BOOLEAN->value => \PDO::PARAM_BOOL,
            default => \PDO::PARAM_STR,
        };

        try {
            $this->stmt->bindValue(param: $param, value: $value, type: $pdoType);
        } catch (\PDOException $e) {
            throw $this->converter->convert($e, $this->stmt->queryString);
        }
    }

    /**
     * @param array<int|string, mixed> $params
     */
    #[\Override]
    public function execute(?array $params = null): Result
    {
        try {
            $this->stmt->execute($params);
        } catch (\PDOException $e) {
            throw $this->converter->convert($e, $this->stmt->queryString);
        }

        return new PdoResult($this->stmt, $this->converter);
    }
}
