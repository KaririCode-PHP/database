<?php

declare(strict_types=1);

namespace KaririCode\Database\Platform;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Enum\TransactionIsolationLevel;
use KaririCode\Database\ValueObject\PlatformVersion;

/**
 * The MySQL and MariaDB dialect: backtick quoting, ON DUPLICATE KEY UPDATE
 * upserts, GET_LOCK advisory locks, session isolation and USE for the
 * tenant switch, with features chosen by version: RETURNING from MariaDB
 * 10.5, CTEs and JSON from MySQL 8.0 and MariaDB 10.2.
 *
 * Preconditions: advisory-lock keys are integers used as lock names for
 * the whole server, across databases, so applications sharing a server
 * need separate key ranges. The locks belong to the session: release them
 * with releaseAdvisoryLock(), or they last until the session ends.
 *
 * Thread-safety: immutable.
 *
 * Side effects: none; methods return SQL text.
 *
 * Example:
 *
 *     $platform = new MySqlPlatform(new PlatformVersion('mariadb', 10, 11));
 *     $platform->getUpsertSql('stock', ['sku', 'qty'], ['sku']);
 *     // INSERT INTO `stock` (`sku`, `qty`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `qty` = VALUES(`qty`)
 *
 * References: ADR-003 (version-aware platforms); SPEC-001 §6.
 *
 * @see https://dev.mysql.com/doc/refman/8.4/en/locking-functions.html
 */
final class MySqlPlatform implements Platform
{
    private const int BLOCKING_LOCK_TIMEOUT_SECONDS = 31_536_000;

    public function __construct(
        public readonly PlatformVersion $version,
    ) {}


    // ── Identifier Quoting ───────────────────────────────────────────────

    #[\Override]
    public function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    // ── SQL Generation ───────────────────────────────────────────────────

    #[\Override]
    public function getLimitSql(string $query, ?int $limit, ?int $offset = null): string
    {
        if ($limit === null) {
            return $query;
        }

        $sql = $query . ' LIMIT ' . $limit;

        if ($offset !== null && $offset > 0) {
            $sql .= ' OFFSET ' . $offset;
        }

        return $sql;
    }

    #[\Override]
    public function currentTimestampSql(): string
    {
        return 'NOW()';
    }

    /**
     * @param list<string> $columns
     * @param list<string> $conflictColumns
     */
    #[\Override]
    public function getUpsertSql(string $table, array $columns, array $conflictColumns): string
    {
        $quoted = array_map(fn(string $c) => $this->quoteIdentifier($c), $columns);
        $placeholders = array_fill(0, count($columns), '?');
        $updates = array_map(
            fn(string $c) => $this->quoteIdentifier($c) . ' = VALUES(' . $this->quoteIdentifier($c) . ')',
            array_diff($columns, $conflictColumns),
        );

        // Sem colunas fora da chave, "col = col" e a atualizacao nula: a linha existente fica.
        if ($updates === []) {
            $first = $this->quoteIdentifier((string) reset($conflictColumns));
            $updates = ["{$first} = {$first}"];
        }

        return sprintf(
            'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
            $this->quoteIdentifier($table),
            implode(', ', $quoted),
            implode(', ', $placeholders),
            implode(', ', $updates),
        );
    }

    /**
     * @param list<string> $columns
     */
    #[\Override]
    public function getBulkInsertSql(string $table, array $columns, int $rowCount): string
    {
        $quoted = array_map(fn(string $c) => $this->quoteIdentifier($c), $columns);
        $rowPlaceholder = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $allRows = implode(', ', array_fill(0, $rowCount, $rowPlaceholder));

        return sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $this->quoteIdentifier($table),
            implode(', ', $quoted),
            $allRows,
        );
    }

    #[\Override]
    public function setTransactionIsolationSql(TransactionIsolationLevel $level): string
    {
        // SESSION: sem ele o nivel vale so para a proxima transacao, e
        // transactionIsolation() passaria a mentir depois dela.
        return 'SET SESSION TRANSACTION ISOLATION LEVEL ' . $level->toSql();
    }

    #[\Override]
    public function getBooleanLiteral(bool $value): string
    {
        return $value ? '1' : '0';
    }

    #[\Override]
    public function getAdvisoryLockSql(int $key): string
    {
        // MariaDB devolve NULL para timeout negativo (o MySQL le como infinito):
        // um ano funciona nos dois.
        return "SELECT GET_LOCK('{$key}', " . self::BLOCKING_LOCK_TIMEOUT_SECONDS . ')';
    }

    #[\Override]
    public function getTryAdvisoryLockSql(int $key): string
    {
        return "SELECT GET_LOCK('{$key}', 0)";
    }

    /**
     * MySQL advisory locks are session-scoped — they persist beyond
     * the transaction. Must be explicitly released.
     * Ref: MySQL docs §14.7.5.5 — GET_LOCK / RELEASE_LOCK
     */
    #[\Override]
    public function getReleaseAdvisoryLockSql(int $key): string
    {
        return "SELECT RELEASE_LOCK('{$key}')";
    }

    #[\Override]
    public function getSetSchemaPathSql(string $schema): string
    {
        return 'USE ' . $this->quoteIdentifier($schema);
    }

    // ── Capability Detection ─────────────────────────────────────────────

    #[\Override]
    public function supportsSavepoints(): bool
    {
        return true;
    }

    #[\Override]
    public function supportsSchemas(): bool
    {
        return false;
    }

    #[\Override]
    public function supportsReturning(): bool
    {
        return $this->version->isMariaDb() && $this->version->gte(10, 5);
    }

    #[\Override]
    public function supportsAdvisoryLocks(): bool
    {
        return true;
    }

    #[\Override]
    public function supportsCommonTableExpressions(): bool
    {
        if ($this->version->isMariaDb()) {
            return $this->version->gte(10, 2);
        }

        return $this->version->gte(8, 0);
    }

    #[\Override]
    public function supportsJsonOperations(): bool
    {
        if ($this->version->isMariaDb()) {
            return $this->version->gte(10, 2);
        }

        return $this->version->gte(5, 7);
    }

    #[\Override]
    public function maxPlaceholders(): int
    {
        return 65535;
    }
}
