<?php

declare(strict_types=1);

namespace KaririCode\Database\Contract;

// ── Statement ────────────────────────────────────────────────────────
//
// Prepared statement abstraction. Wraps PDOStatement (or native
// driver equivalent). Parameter binding is type-safe via
// ParameterType enum.
// ─────────────────────────────────────────────────────────────────────

interface Statement
{
    public function bindValue(int|string $param, mixed $value, int $type = 2): void;

    /**
     * @param array<int|string, mixed> $params
     */
    public function execute(?array $params = null): Result;
}
