<?php

declare(strict_types=1);

namespace KaririCode\Database\Contract;

use KaririCode\Database\Cache\CacheProfile;
use KaririCode\Database\Enum\TransactionIsolationLevel;

/**
 * The connection the ORM, DataFlow and Tenancy program against: queries
 * and statements with bound parameters, nested transactions through
 * savepoints, session isolation, advisory locks, result caching, a query
 * builder and convenience DML.
 *
 * Preconditions: SQL text and identifiers come from code. Values are
 * bound, but the table and column names given to insert(), update() and
 * delete() are written into the SQL unquoted. executeStatement() is for
 * statements without a result set: without parameters it runs through
 * exec(), and a SELECT there leaves its result pending on MySQL.
 *
 * Thread-safety: an implementation holds one session and is not safe to
 * share between concurrent requests.
 *
 * Side effects: every method except transactionNestingLevel(),
 * transactionIsolation() and createQueryBuilder() talks to the server.
 *
 * Example:
 *
 *     $connection->transactional(static function (Connection $c): void {
 *         $c->insert('orders', ['customer_id' => 7, 'total' => '99.90']);
 *         $c->update('stock', ['reserved' => 1], ['sku' => 'A-1']);
 *     });
 *
 * References: ADR-001 (two layers); ADR-005 (savepoints); ADR-010 (result
 * cache); SPEC-001 §2.1 and §4.
 *
 * @see https://www.php.net/manual/en/book.pdo.php
 */
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
