<?php

declare(strict_types=1);

namespace KaririCode\Database\Contract;

/**
 * The thin session a Driver returns: prepare, exec, the last insert id and
 * the native transaction calls, without nesting, events or caching, which
 * DefaultConnection builds on top.
 *
 * Preconditions: beginTransaction(), commit() and rollBack() are single
 * level; nesting is DefaultConnection's job.
 *
 * Thread-safety: one session; not safe to share.
 *
 * Side effects: every method talks to the server; errors arrive as
 * package exceptions, never as driver exceptions (ADR-004).
 *
 * Example:
 *
 *     $session->prepare('SELECT 1')->execute()->fetchOne();
 *
 * References: ADR-001; ADR-004; SPEC-001 §2.1.
 *
 * @see https://www.php.net/manual/en/class.pdo.php
 */
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
