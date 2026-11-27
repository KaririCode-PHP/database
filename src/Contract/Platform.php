<?php

declare(strict_types=1);

namespace KaririCode\Database\Contract;

use KaririCode\Database\Enum\TransactionIsolationLevel;
use KaririCode\Database\ValueObject\PlatformVersion;

/**
 * The SQL dialect of one vendor and version: identifier quoting, upsert
 * and bulk-insert SQL, isolation and advisory-lock statements, literals,
 * and what the server supports (savepoints, schemas, RETURNING, CTEs,
 * JSON), so no other class writes vendor SQL.
 *
 * Preconditions: names given to the SQL builders are table and column
 * names from code, escaped by quoteIdentifier(); values never pass through
 * the platform, they are bound.
 *
 * Thread-safety: immutable.
 *
 * Side effects: none; every method returns SQL text or a capability.
 *
 * Example:
 *
 *     $sql = $connection->platform->getUpsertSql('stock', ['sku', 'qty'], ['sku']);
 *     $connection->executeStatement($sql, ['A-1', 5]);
 *
 * References: ADR-003 (version-aware platforms); SPEC-001 §6.
 *
 * @see https://www.postgresql.org/docs/current/sql-insert.html#SQL-ON-CONFLICT
 */
interface Platform
{
    public PlatformVersion $version { get; }

    // ── Identifier Quoting ───────────────────────────────────────────────

    public function quoteIdentifier(string $identifier): string;

    // ── SQL Generation ───────────────────────────────────────────────────

    public function getLimitSql(string $query, ?int $limit, ?int $offset = null): string;

    public function currentTimestampSql(): string;

    /**
     * @param list<string> $columns
     * @param list<string> $conflictColumns
     */
    public function getUpsertSql(string $table, array $columns, array $conflictColumns): string;

    /**
     * @param list<string> $columns
     */
    public function getBulkInsertSql(string $table, array $columns, int $rowCount): string;

    public function setTransactionIsolationSql(TransactionIsolationLevel $level): string;

    public function getBooleanLiteral(bool $value): string;

    public function getAdvisoryLockSql(int $key): string;

    public function getTryAdvisoryLockSql(int $key): string;

    /** SQL que libera um lock de sessao, ou null quando o lock e de escopo transacional. */
    public function getReleaseAdvisoryLockSql(int $key): ?string;

    public function getSetSchemaPathSql(string $schema): string;

    // ── Capability Detection ─────────────────────────────────────────────

    public function supportsSavepoints(): bool;

    public function supportsSchemas(): bool;

    public function supportsReturning(): bool;

    public function supportsAdvisoryLocks(): bool;

    public function supportsCommonTableExpressions(): bool;

    public function supportsJsonOperations(): bool;

    public function maxPlaceholders(): int;
}
