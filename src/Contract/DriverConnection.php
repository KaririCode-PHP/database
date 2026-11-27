<?php

declare(strict_types=1);

namespace KaririCode\Database\Contract;

// ── DriverConnection ─────────────────────────────────────────────────
//
// Thin, extension-specific connection. Created by Driver.
// The Connection layer wraps this with rich functionality
// (transaction nesting, type conversion, advisory locks).
// ─────────────────────────────────────────────────────────────────────

interface DriverConnection
{
    public function prepare(string $sql): Statement;

    public function exec(string $sql): int;

    public function lastInsertId(?string $name = null): string;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollBack(): void;

    public function nativeConnection(): object;
}
