<?php

declare(strict_types=1);

namespace KaririCode\Database\Platform;

use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Enum\TransactionIsolationLevel;
use KaririCode\Database\Exception\PlatformException;
use KaririCode\Database\ValueObject\PlatformVersion;

final class SqlitePlatform implements Platform
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
        return "datetime('now')";
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
            fn(string $c) => $this->quoteIdentifier($c) . ' = excluded.' . $this->quoteIdentifier($c),
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
        return match ($level) {
            TransactionIsolationLevel::ReadUncommitted => 'PRAGMA read_uncommitted = ON',
            TransactionIsolationLevel::Serializable => 'PRAGMA read_uncommitted = OFF',
            default => throw PlatformException::unsupportedFeature('isolation level ' . $level->toSql()),
        };
    }

    #[\Override]
    public function getBooleanLiteral(bool $value): string
    {
        return $value ? '1' : '0';
    }

    #[\Override]
    public function getAdvisoryLockSql(int $key): string
    {
        throw PlatformException::advisoryLocksNotSupported();
    }

    #[\Override]
    public function getTryAdvisoryLockSql(int $key): string
    {
        throw PlatformException::advisoryLocksNotSupported();
    }

    #[\Override]
    public function getSetSchemaPathSql(string $schema): string
    {
        throw PlatformException::schemasNotSupported();
    }

    #[\Override]
    public function supportsSavepoints(): bool
    {
        return $this->version->gte(3, 6);
    }

    #[\Override]
    public function supportsSchemas(): bool
    {
        return false;
    }

    #[\Override]
    public function supportsReturning(): bool
    {
        return $this->version->gte(3, 35);
    }

    #[\Override]
    public function supportsAdvisoryLocks(): bool
    {
        return false;
    }

    #[\Override]
    public function supportsCommonTableExpressions(): bool
    {
        return $this->version->gte(3, 8);
    }

    #[\Override]
    public function supportsJsonOperations(): bool
    {
        return $this->version->gte(3, 38);
    }

    #[\Override]
    public function maxPlaceholders(): int
    {
        // SQLITE_MAX_VARIABLE_NUMBER: 999 antes do 3.32.0; 32766 a partir dele.
        // Medido no SQLite 3.45.2: 32766 aceito, 32767 rejeitado.
        return $this->version->gte(3, 32) ? 32766 : 999;
    }

    /** pg_advisory_xact_lock e liberado pelo banco no fim da transacao; SQLite nao tem locks consultivos. */
    #[\Override]
    public function getReleaseAdvisoryLockSql(int $key): ?string
    {
        return null;
    }
}
