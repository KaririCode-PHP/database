<?php

declare(strict_types=1);

namespace KaririCode\Database\Platform;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Enum\TransactionIsolationLevel;
use KaririCode\Database\ValueObject\PlatformVersion;

final class PostgreSqlPlatform implements Platform
{
    public function __construct(
        public readonly PlatformVersion $version,
    ) {}


    #[\Override]
    public function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

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
        return 'CURRENT_TIMESTAMP';
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
        $conflict = array_map(fn(string $c) => $this->quoteIdentifier($c), $conflictColumns);
        $updates = array_map(
            fn(string $c) => $this->quoteIdentifier($c) . ' = EXCLUDED.' . $this->quoteIdentifier($c),
            array_diff($columns, $conflictColumns),
        );

        return sprintf(
            'INSERT INTO %s (%s) VALUES (%s) ON CONFLICT (%s) DO UPDATE SET %s',
            $this->quoteIdentifier($table),
            implode(', ', $quoted),
            implode(', ', $placeholders),
            implode(', ', $conflict),
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
        // PgSQL expects spaces, not underscores: 'read committed', not 'read_committed'
        $levelStr = strtolower($level->toSql());

        return "SET default_transaction_isolation TO '{$levelStr}'";
    }

    #[\Override]
    public function getBooleanLiteral(bool $value): string
    {
        return $value ? 'TRUE' : 'FALSE';
    }

    #[\Override]
    public function getAdvisoryLockSql(int $key): string
    {
        return "SELECT pg_advisory_xact_lock({$key})";
    }

    #[\Override]
    public function getTryAdvisoryLockSql(int $key): string
    {
        return "SELECT pg_try_advisory_xact_lock({$key})";
    }

    #[\Override]
    public function getSetSchemaPathSql(string $schema): string
    {
        return "SET search_path TO {$this->quoteIdentifier($schema)}, public";
    }

    #[\Override]
    public function supportsSavepoints(): bool
    {
        return true;
    }

    #[\Override]
    public function supportsSchemas(): bool
    {
        return true;
    }

    #[\Override]
    public function supportsReturning(): bool
    {
        return true;
    }

    #[\Override]
    public function supportsAdvisoryLocks(): bool
    {
        return true;
    }

    #[\Override]
    public function supportsCommonTableExpressions(): bool
    {
        return true;
    }

    #[\Override]
    public function supportsJsonOperations(): bool
    {
        return $this->version->gte(9, 4);
    }

    #[\Override]
    public function maxPlaceholders(): int
    {
        // Limite do libpq: PQ_QUERY_PARAM_MAX_LIMIT = 65535 (libpq-fe.h).
        return 65535;
    }

    /** pg_advisory_xact_lock e liberado pelo banco no fim da transacao; SQLite nao tem locks consultivos. */
    #[\Override]
    public function getReleaseAdvisoryLockSql(int $key): ?string
    {
        return null;
    }
}
