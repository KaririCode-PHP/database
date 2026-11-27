<?php

declare(strict_types=1);

namespace KaririCode\Database\Connection;

use KaririCode\Database\Cache\CachedResult;
use KaririCode\Database\Cache\CacheProfile;
use KaririCode\Database\Contract\Connection;
use KaririCode\Database\Contract\DriverConnection;
use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\QueryBuilder;
use KaririCode\Database\Contract\Result;
use KaririCode\Database\Enum\ParameterType;
use KaririCode\Database\Enum\TransactionIsolationLevel;
use KaririCode\Database\Event\ConnectionError;
use KaririCode\Database\Event\QueryExecuted;
use KaririCode\Database\Event\TransactionBegun;
use KaririCode\Database\Event\TransactionCommitted;
use KaririCode\Database\Event\TransactionRolledBack;
use KaririCode\Database\Exception\PlatformException;
use KaririCode\Database\Exception\TransactionException;
use KaririCode\Database\Query\SqlQueryBuilder;
use KaririCode\Database\Type\ScalarValue;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * The connection applications use: runs queries and statements over a
 * driver session, nests transactions through savepoints, sets the session
 * isolation, takes advisory locks, caches query results through PSR-16 and
 * reports what it does as PSR-14 events.
 *
 * Preconditions: SQL and identifiers come from code; values are bound.
 * insert(), update() and delete() write table and column names unquoted.
 * A nested beginTransaction() needs a platform with savepoints
 * (PlatformException 6081). advisoryLock() blocks until the lock is free;
 * on PostgreSQL the locks are transaction-scoped, so take them inside a
 * transaction.
 *
 * Thread-safety: one session with a nesting counter; not safe to share
 * between concurrent requests.
 *
 * Side effects: talks to the server; dispatches QueryExecuted,
 * ConnectionError and the transaction events, whose payloads include the
 * bound values; executeCacheQuery() reads and writes the cache. A failed
 * statement is rethrown after its ConnectionError event.
 *
 * Example:
 *
 *     $total = $connection->transactional(static function (DefaultConnection $c): mixed {
 *         $c->executeStatement('UPDATE accounts SET balance = balance - ? WHERE id = ?', ['10.00', 7]);
 *         return $c->executeQuery('SELECT balance FROM accounts WHERE id = ?', [7])->fetchOne();
 *     });
 *
 * References: ADR-001; ADR-005 (savepoints, BUG-006); ADR-010 (result
 * cache); ADR-013 (events); SPEC-001 §4.
 *
 * @see https://www.postgresql.org/docs/current/explicit-locking.html#ADVISORY-LOCKS
 */
final class DefaultConnection implements Connection
{
    private const string CACHE_MISS = "\0kariricode.database.cache-miss";

    private int $transactionNestingLevel = 0;
    private TransactionIsolationLevel $isolationLevel = TransactionIsolationLevel::ReadCommitted;

    public function __construct(
        private readonly DriverConnection $driverConnection,
        public readonly Platform $platform,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly ?CacheInterface $defaultCache = null,
    ) {}

    // ── Query Execution ──────────────────────────────────────────────────

    /**
     * @param array<int|string, int> $types
     * @param array<int|string, mixed> $params
     */
    #[\Override]
    public function executeQuery(
        string $sql,
        array $params = [],
        array $types = [],
    ): Result {
        $start = hrtime(true);

        try {
            $stmt = $this->driverConnection->prepare($sql);
            $this->bindParameters(stmt: $stmt, params: $params, types: $types);
            $result = $stmt->execute();

            $this->emit(new QueryExecuted(sql: $sql, params: $params, types: $types, executionTimeMs: (hrtime(true) - $start) / 1_000_000, rowCount: $result->rowCount()));

            return $result;
        } catch (\Throwable $e) {
            $this->emit(new ConnectionError(exception: $e, sql: $sql, params: $params));

            throw $e;
        }
    }

    /**
     * @param array<int|string, int> $types
     * @param array<int|string, mixed> $params
     */
    #[\Override]
    public function executeCacheQuery(
        string $sql,
        array $params,
        array $types,
        CacheProfile $cacheProfile,
    ): Result {
        $cache = $cacheProfile->cache ?? $this->defaultCache;

        if ($cache === null) {
            return $this->executeQuery(sql: $sql, params: $params, types: $types);
        }

        $cacheKey = $cacheProfile->generateCacheKey(sql: $sql, params: $params, types: $types);

        // PSR-16: a sentinela distingue "ausente" de um array vazio guardado.
        $rows = $this->cachedRows($cache->get($cacheKey, self::CACHE_MISS));

        if ($rows !== null) {
            return new CachedResult($rows);
        }

        $rows = $this->executeQuery(sql: $sql, params: $params, types: $types)->fetchAllAssociative();
        $cache->set(key: $cacheKey, value: $rows, ttl: $cacheProfile->ttl > 0 ? $cacheProfile->ttl : null);

        return new CachedResult($rows);
    }

    /**
     * @param array<int|string, int> $types
     * @param array<int|string, mixed> $params
     */
    #[\Override]
    public function executeStatement(
        string $sql,
        array $params = [],
        array $types = [],
    ): int {
        $start = hrtime(true);

        try {
            if (empty($params)) {
                $affected = $this->driverConnection->exec($sql);
            } else {
                $stmt = $this->driverConnection->prepare($sql);
                $this->bindParameters(stmt: $stmt, params: $params, types: $types);
                $result = $stmt->execute();
                $affected = $result->rowCount();
            }

            $duration = (hrtime(true) - $start) / 1_000_000;
            $this->emit(new QueryExecuted(sql: $sql, params: $params, types: $types, executionTimeMs: $duration, rowCount: $affected));

            return $affected;
        } catch (\Throwable $e) {
            $this->emit(new ConnectionError(exception: $e, sql: $sql, params: $params));

            throw $e;
        }
    }

    // ── Transactions ─────────────────────────────────────────────────────

    #[\Override]
    public function beginTransaction(): void
    {
        // Verify savepoint support before mutating state (nested case only).
        if ($this->transactionNestingLevel > 0 && !$this->platform->supportsSavepoints()) {
            throw PlatformException::savepointsNotSupported();
        }

        ++$this->transactionNestingLevel;

        if ($this->transactionNestingLevel === 1) {
            $this->driverConnection->beginTransaction();
        } else {
            // Savepoint name uses the post-increment level so commit() and
            // rollBack() — which read the level before decrement — reference
            // the same name. Without this, begin creates sp_{n-1} but commit
            // releases sp_{n}, causing "savepoint does not exist" on nested
            // transactions of depth >= 2. (BUG-006)
            $this->createSavepoint($this->getSavepointName());
        }

        $this->emit(new TransactionBegun($this->transactionNestingLevel));
    }

    #[\Override]
    public function commit(): void
    {
        if ($this->transactionNestingLevel === 0) {
            throw TransactionException::noActiveTransaction();
        }

        if ($this->transactionNestingLevel === 1) {
            $this->driverConnection->commit();
        } else {
            $this->releaseSavepoint($this->getSavepointName());
        }

        --$this->transactionNestingLevel;
        $this->emit(new TransactionCommitted($this->transactionNestingLevel));
    }

    #[\Override]
    public function rollBack(): void
    {
        if ($this->transactionNestingLevel === 0) {
            throw TransactionException::noActiveTransaction();
        }

        if ($this->transactionNestingLevel === 1) {
            $this->driverConnection->rollBack();
            $toSavepoint = false;
        } else {
            $this->rollBackSavepoint($this->getSavepointName());
            $toSavepoint = true;
        }

        --$this->transactionNestingLevel;
        $this->emit(new TransactionRolledBack($this->transactionNestingLevel, $toSavepoint));
    }

    #[\Override]
    public function transactional(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            return $this->commitAfter($callback($this));
        } catch (\Throwable $e) {
            $this->rollBack();

            throw $e;
        }
    }

    #[\Override]
    public function isTransactionActive(): bool
    {
        return $this->transactionNestingLevel > 0;
    }

    #[\Override]
    public function transactionNestingLevel(): int
    {
        return $this->transactionNestingLevel;
    }

    // ── Savepoints ───────────────────────────────────────────────────────

    #[\Override]
    public function createSavepoint(string $name): void
    {
        $this->driverConnection->exec('SAVEPOINT ' . $name);
    }

    #[\Override]
    public function releaseSavepoint(string $name): void
    {
        $this->driverConnection->exec('RELEASE SAVEPOINT ' . $name);
    }

    #[\Override]
    public function rollBackSavepoint(string $name): void
    {
        $this->driverConnection->exec('ROLLBACK TO SAVEPOINT ' . $name);
    }

    // ── Isolation Level ──────────────────────────────────────────────────

    #[\Override]
    public function setTransactionIsolation(TransactionIsolationLevel $level): void
    {
        $sql = $this->platform->setTransactionIsolationSql($level);
        $this->driverConnection->exec($sql);
        $this->isolationLevel = $level;
    }

    #[\Override]
    public function transactionIsolation(): TransactionIsolationLevel
    {
        return $this->isolationLevel;
    }

    // ── Advisory Locks ───────────────────────────────────────────────────

    #[\Override]
    public function advisoryLock(int $key): void
    {
        if (!$this->platform->supportsAdvisoryLocks()) {
            throw PlatformException::advisoryLocksNotSupported();
        }

        // SELECT-based: must use prepare+execute to consume result set
        $sql = $this->platform->getAdvisoryLockSql($key);
        $stmt = $this->driverConnection->prepare($sql);
        $result = $stmt->execute();
        $acquired = self::blockingLockAcquired($result->fetchOne());
        $result->free();

        if (!$acquired) {
            throw TransactionException::advisoryLockNotAcquired();
        }
    }

    #[\Override]
    public function tryAdvisoryLock(int $key): bool
    {
        if (!$this->platform->supportsAdvisoryLocks()) {
            throw PlatformException::advisoryLocksNotSupported();
        }

        $sql = $this->platform->getTryAdvisoryLockSql($key);
        $stmt = $this->driverConnection->prepare($sql);
        $result = $stmt->execute();
        // 't'/'f' do PostgreSQL em texto: (bool) 'f' seria true.
        $acquired = ScalarValue::asBool($result->fetchOne() ?? false, 'boolean');
        $result->free();

        return $acquired;
    }

    /**
     * Releases a session-scoped advisory lock (MySQL only).
     * PostgreSQL advisory locks are transaction-scoped and auto-release
     * on commit/rollback — calling this on PgSQL is a no-op.
     */
    #[\Override]
    public function releaseAdvisoryLock(int $key): bool
    {
        if (!$this->platform->supportsAdvisoryLocks()) {
            return false;
        }

        $sql = $this->platform->getReleaseAdvisoryLockSql($key);

        // Lock de escopo transacional (PostgreSQL): o banco libera no fim da transacao.
        if ($sql === null) {
            return true;
        }
        $stmt = $this->driverConnection->prepare($sql);
        $result = $stmt->execute();
        // 't'/'f' do PostgreSQL em texto: (bool) 'f' seria true.
        $acquired = ScalarValue::asBool($result->fetchOne() ?? false, 'boolean');
        $result->free();

        return $acquired;
    }

    // ── Platform & Configuration ─────────────────────────────────────────


    #[\Override]
    public function nativeConnection(): object
    {
        return $this->driverConnection->nativeConnection();
    }

    // ── Query Builder Factory ────────────────────────────────────────────

    #[\Override]
    public function createQueryBuilder(): QueryBuilder
    {
        return new SqlQueryBuilder($this);
    }

    // ── Utilities ────────────────────────────────────────────────────────

    #[\Override]
    public function lastInsertId(?string $name = null): string
    {
        return $this->driverConnection->lastInsertId($name);
    }

    #[\Override]
    public function ping(): bool
    {
        try {
            // prepare/execute e free(): exec() com SELECT deixa o resultado pendente
            // no MySQL e a consulta seguinte falha com o erro 2014.
            $result = $this->driverConnection->prepare('SELECT 1')->execute();
            $result->free();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // ── Convenience DML ──────────────────────────────────────────────────

    /**
     * @param array<int|string, int> $types
     * @param array<string, mixed> $data
     */
    #[\Override]
    public function insert(string $table, array $data, array $types = []): int
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders),
        );

        return $this->executeStatement(sql: $sql, params: array_values($data), types: $types);
    }

    /**
     * @param array<int|string, int> $types
     * @param array<string, mixed> $criteria
     * @param array<string, mixed> $data
     */
    #[\Override]
    public function update(string $table, array $data, array $criteria, array $types = []): int
    {
        $sets = array_map(fn(string $col) => "{$col} = ?", array_keys($data));
        $wheres = array_map(fn(string $col) => "{$col} = ?", array_keys($criteria));

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $sets),
            implode(' AND ', $wheres),
        );

        return $this->executeStatement(
            sql: $sql,
            params: array_merge(array_values($data), array_values($criteria)),
            types: $types,
        );
    }

    /**
     * @param array<int|string, int> $types
     * @param array<string, mixed> $criteria
     */
    #[\Override]
    public function delete(string $table, array $criteria, array $types = []): int
    {
        $wheres = array_map(fn(string $col) => "{$col} = ?", array_keys($criteria));

        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $table,
            implode(' AND ', $wheres),
        );

        return $this->executeStatement(sql: $sql, params: array_values($criteria), types: $types);
    }

    // ── Private Helpers ──────────────────────────────────────────────────

    /**
     * @param array<int|string, int> $types
     * @param array<int|string, mixed> $params
     */
    private function bindParameters(
        \KaririCode\Database\Contract\Statement $stmt,
        array $params,
        array $types,
    ): void {
        foreach (array_keys($params) as $key) {
            $stmt->bindValue(param: is_int($key) ? $key + 1 : $key, value: $params[$key], type: $types[$key] ?? ParameterType::STRING->value);
        }
    }

    private function getSavepointName(): string
    {
        return 'kariricode_sp_' . $this->transactionNestingLevel;
    }


    private function emit(object $event): void
    {
        $this->eventDispatcher?->dispatch($event);
    }

    /** @return list<array<string, mixed>>|null entrada adulterada ou de outro formato conta como ausente */
    private function cachedRows(mixed $cached): ?array
    {
        if (!is_array($cached) || !array_is_list($cached) || array_filter($cached, static fn(mixed $row): bool => !is_array($row)) !== []) {
            return null;
        }

        /** @var list<array<string, mixed>> $cached */
        return $cached;
    }

    private function commitAfter(mixed $result): mixed
    {
        $this->commit();

        return $result;
    }

    /** GET_LOCK devolve 1, 0 (timeout) ou NULL (erro); pg_advisory_xact_lock devolve void. */
    private static function blockingLockAcquired(mixed $value): bool
    {
        return $value !== null && $value !== false && $value !== 0 && $value !== '0';
    }
}
