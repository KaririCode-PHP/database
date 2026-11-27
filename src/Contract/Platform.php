<?php

declare(strict_types=1);

namespace KaririCode\Database\Contract;

use KaririCode\Database\Enum\TransactionIsolationLevel;
use KaririCode\Database\ValueObject\PlatformVersion;

// ── Platform ─────────────────────────────────────────────────────────
//
// SQL dialect abstraction. Each supported database has one
// implementation. Version-aware via PlatformVersion — no inheritance
// chain (anti-Doctrine: no 2K-line AbstractPlatform).
// ─────────────────────────────────────────────────────────────────────

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
