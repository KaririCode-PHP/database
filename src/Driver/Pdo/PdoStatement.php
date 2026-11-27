<?php

declare(strict_types=1);

namespace KaririCode\Database\Driver\Pdo;

use KaririCode\Database\Contract\Result;
use KaririCode\Database\Contract\Statement;
use KaririCode\Database\Enum\ParameterType;

final class PdoStatement implements Statement
{
    public function __construct(
        private readonly \PDOStatement $stmt,
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

        $this->stmt->bindValue(param: $param, value: $value, type: $pdoType);
    }

    /**
     * @param array<int|string, mixed> $params
     */
    #[\Override]
    public function execute(?array $params = null): Result
    {
        $this->stmt->execute($params);

        return new PdoResult($this->stmt);
    }
}
