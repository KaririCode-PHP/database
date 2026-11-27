<?php

declare(strict_types=1);

namespace KaririCode\Database\Connection;

use KaririCode\Database\Cache\CacheProfile;
use KaririCode\Database\Contract\Connection;
use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Contract\QueryBuilder;
use KaririCode\Database\Contract\Result;
use KaririCode\Database\Enum\TransactionIsolationLevel;

// ── ReadWriteConnection ──────────────────────────────────────────────
//
// Transparent read/write routing decorator.
//
// Routing rules (ref: Doctrine PrimaryReadReplicaConnection):
// - executeQuery()     → replica (if not in primary-only mode)
// - executeStatement() → primary (forces primary-only mode)
// - beginTransaction() → primary (forces primary-only for duration)
// - Sticky: once primary is used, all ops go to primary
//
// Ref: Doctrine DBAL issue #9225 — sequence reads fail on replica
// Ref: Symfony AI issue #1283 — DBAL routing rules documented
// ─────────────────────────────────────────────────────────────────────

final class ReadWriteConnection implements Connection
{
    public readonly Platform $platform;

    private bool $primaryOnly = false;
    private ?Connection $activeReplica = null;

    /**
     * @param Connection $write Primary connection
     * @param array<int, Connection> $reads Replica connections
     */
    public function __construct(
        private readonly Connection $write,
        private readonly array $reads,
        private readonly bool $sticky = true,
    ) {
        // A plataforma e a do primario e nao muda: valor fixo, exposto como propriedade.
        $this->platform = $write->platform;
    }

    // ── Query Execution ──────────────────────────────────────────────────

    /**
     * @param array<int|string, int> $types
     * @param array<int|string, mixed> $params
     */
    #[\Override]
    public function executeQuery(string $sql, array $params = [], array $types = []): Result
    {
        return $this->getReadConnection()->executeQuery(sql: $sql, params: $params, types: $types);
    }

    /**
     * @param array<int|string, int> $types
     * @param array<int|string, mixed> $params
     */
    #[\Override]
    public function executeCacheQuery(string $sql, array $params, array $types, CacheProfile $cacheProfile): Result
    {
        return $this->getReadConnection()->executeCacheQuery(sql: $sql, params: $params, types: $types, cacheProfile: $cacheProfile);
    }

    /**
     * @param array<int|string, int> $types
     * @param array<int|string, mixed> $params
     */
    #[\Override]
    public function executeStatement(string $sql, array $params = [], array $types = []): int
    {
        $this->markWrite();

        return $this->write->executeStatement(sql: $sql, params: $params, types: $types);
    }

    // ── Transactions (always primary) ────────────────────────────────────

    #[\Override]
    public function beginTransaction(): void
    {
        $this->markWrite();
        $this->write->beginTransaction();
    }

    #[\Override]
    public function commit(): void
    {
        $this->write->commit();
    }

    #[\Override]
    public function rollBack(): void
    {
        $this->write->rollBack();
    }

    #[\Override]
    public function transactional(callable $callback): mixed
    {
        $this->markWrite();

        return $this->write->transactional($callback);
    }

    #[\Override]
    public function isTransactionActive(): bool
    {
        return $this->write->isTransactionActive();
    }

    #[\Override]
    public function transactionNestingLevel(): int
    {
        return $this->write->transactionNestingLevel();
    }

    // ── Savepoints ───────────────────────────────────────────────────────

    #[\Override]
    public function createSavepoint(string $name): void
    {
        $this->write->createSavepoint($name);
    }
    #[\Override]
    public function releaseSavepoint(string $name): void
    {
        $this->write->releaseSavepoint($name);
    }
    #[\Override]
    public function rollBackSavepoint(string $name): void
    {
        $this->write->rollBackSavepoint($name);
    }

    // ── Isolation / Advisory ─────────────────────────────────────────────

    #[\Override]
    public function setTransactionIsolation(TransactionIsolationLevel $level): void
    {
        $this->write->setTransactionIsolation($level);
    }

    #[\Override]
    public function transactionIsolation(): TransactionIsolationLevel
    {
        return $this->write->transactionIsolation();
    }

    #[\Override]
    public function advisoryLock(int $key): void
    {
        $this->write->advisoryLock($key);
    }
    #[\Override]
    public function tryAdvisoryLock(int $key): bool
    {
        return $this->write->tryAdvisoryLock($key);
    }
    #[\Override]
    public function releaseAdvisoryLock(int $key): bool
    {
        return $this->write->releaseAdvisoryLock($key);
    }

    // ── Platform & Utilities ─────────────────────────────────────────────

    #[\Override]
    public function nativeConnection(): object
    {
        return $this->write->nativeConnection();
    }
    #[\Override]
    public function createQueryBuilder(): QueryBuilder
    {
        return $this->write->createQueryBuilder();
    }
    #[\Override]
    public function lastInsertId(?string $name = null): string
    {
        return $this->write->lastInsertId($name);
    }

    #[\Override]
    public function ping(): bool
    {
        return $this->write->ping();
    }

    // ── Convenience DML ──────────────────────────────────────────────────

    /**
     * @param array<int|string, int> $types
     * @param array<string, mixed> $data
     */
    #[\Override]
    public function insert(string $table, array $data, array $types = []): int
    {
        $this->markWrite();

        return $this->write->insert(table: $table, data: $data, types: $types);
    }

    /**
     * @param array<int|string, int> $types
     * @param array<string, mixed> $criteria
     * @param array<string, mixed> $data
     */
    #[\Override]
    public function update(string $table, array $data, array $criteria, array $types = []): int
    {
        $this->markWrite();

        return $this->write->update(table: $table, data: $data, criteria: $criteria, types: $types);
    }

    /**
     * @param array<int|string, int> $types
     * @param array<string, mixed> $criteria
     */
    #[\Override]
    public function delete(string $table, array $criteria, array $types = []): int
    {
        $this->markWrite();

        return $this->write->delete(table: $table, criteria: $criteria, types: $types);
    }

    // ── Explicit Routing ─────────────────────────────────────────────────

    public function ensureConnectedToPrimary(): void
    {
        $this->primaryOnly = true;
    }

    public function ensureConnectedToReplica(): void
    {
        if (!$this->write->isTransactionActive()) {
            $this->primaryOnly = false;
        }
    }

    // ── Private ──────────────────────────────────────────────────────────

    /**
     * Registra que houve escrita ou abertura de transacao.
     *
     * sticky=true (padrao, ADR-006): o primario passa a atender todas as
     * leituras pelo resto do ciclo da requisicao, evitando leitura defasada
     * por lag de replicacao (Doctrine DBAL 4.2 PrimaryReadReplicaConnection;
     * Laravel 12.x, opcao "sticky").
     *
     * sticky=false: so a transacao ativa forca o primario; fora dela as
     * leituras voltam para a replica. Use apenas se a aplicacao tolera
     * leitura defasada logo apos uma escrita.
     */
    private function markWrite(): void
    {
        if ($this->sticky) {
            $this->primaryOnly = true;
        }
    }

    private function getReadConnection(): Connection
    {
        // Invariante do ADR-006: dentro de transacao, tudo vai para o primario.
        if ($this->primaryOnly || empty($this->reads) || $this->write->isTransactionActive()) {
            return $this->write;
        }

        if ($this->activeReplica === null) {
            $this->activeReplica = $this->reads[array_rand($this->reads)];
        }

        return $this->activeReplica;
    }
}
