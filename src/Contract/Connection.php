<?php

declare(strict_types=1);

namespace KaririCode\Database\Contract;

use KaririCode\Database\Cache\CacheProfile;
use KaririCode\Database\Enum\TransactionIsolationLevel;

// ── Connection ───────────────────────────────────────────────────────
//
// Primary consumer-facing contract for database operations.
// Consumers (ORM, DataFlow, Tenancy) program against this interface.
// The DefaultConnection implementation wraps a DriverConnection
// with transaction nesting, type conversion, and advisory locks.
//
// Phase 1: local in kariricode/database
// Phase 2: promoted to KaririCode\Contract\Database\Connection
// ─────────────────────────────────────────────────────────────────────

interface Connection
{
    // ── Query Execution ──────────────────────────────────────────────────

    /**
     * @param array<int|string, int> $types
     * @param array<int|string, mixed> $params
     */
    public function executeQuery(
        string $sql,
        array $params = [],
        array $types = [],
    ): Result;

    /**
     * @param array<int|string, int> $types
     * @param array<int|string, mixed> $params
     */
    public function executeCacheQuery(
        string $sql,
        array $params,
        array $types,
        CacheProfile $cacheProfile,
    ): Result;

    /**
     * @param array<int|string, int> $types
     * @param array<int|string, mixed> $params
     */
    public function executeStatement(
        string $sql,
        array $params = [],
        array $types = [],
    ): int;

    // ── Transactions ─────────────────────────────────────────────────────

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollBack(): void;

    public function transactional(callable $callback): mixed;

    public function isTransactionActive(): bool;

    public function transactionNestingLevel(): int;

    // ── Savepoints ───────────────────────────────────────────────────────

    public function createSavepoint(string $name): void;

    public function releaseSavepoint(string $name): void;

    public function rollBackSavepoint(string $name): void;

    // ── Isolation Level ──────────────────────────────────────────────────

    public function setTransactionIsolation(TransactionIsolationLevel $level): void;

    public function transactionIsolation(): TransactionIsolationLevel;

    // ── Advisory Locks ───────────────────────────────────────────────────

    public function advisoryLock(int $key): void;

    public function tryAdvisoryLock(int $key): bool;

    /**
     * Releases a session-scoped advisory lock (MySQL).
     * On PostgreSQL, advisory locks are transaction-scoped and auto-release —
     * calling this is a no-op returning true.
     */
    public function releaseAdvisoryLock(int $key): bool;

    // ── Platform & Configuration ─────────────────────────────────────────

    public Platform $platform { get; }

    public function nativeConnection(): object;

    // ── Query Builder Factory ────────────────────────────────────────────

    public function createQueryBuilder(): QueryBuilder;

    // ── Utilities ────────────────────────────────────────────────────────

    public function lastInsertId(?string $name = null): string;

    public function ping(): bool;

    // ── Convenience DML ──────────────────────────────────────────────────

    /**
     * @return int Affected rows
     * @param array<int|string, int> $types
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data, array $types = []): int;

    /**
     * @return int Affected rows
     * @param array<int|string, int> $types
     * @param array<string, mixed> $criteria
     * @param array<string, mixed> $data
     */
    public function update(string $table, array $data, array $criteria, array $types = []): int;

    /**
     * @return int Affected rows
     * @param array<int|string, int> $types
     * @param array<string, mixed> $criteria
     */
    public function delete(string $table, array $criteria, array $types = []): int;
}
