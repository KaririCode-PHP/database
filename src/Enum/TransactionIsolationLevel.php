<?php

declare(strict_types=1);

namespace KaririCode\Database\Enum;

/**
 * The four SQL isolation levels, with the SQL name each platform writes
 * into its SET statement.
 *
 * Preconditions: SQLite offers only ReadUncommitted and Serializable; the
 * others raise PlatformException 6080 there.
 *
 * Thread-safety: an enum; immutable.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $connection->setTransactionIsolation(TransactionIsolationLevel::RepeatableRead);
 *
 * References: SPEC-001 §4.2 (GAP-DB-04).
 *
 * @see https://www.postgresql.org/docs/current/transaction-iso.html
 */
enum TransactionIsolationLevel: int
{
    case ReadUncommitted = 1;
    case ReadCommitted = 2;
    case RepeatableRead = 3;
    case Serializable = 4;

    public function toSql(): string
    {
        return match ($this) {
            self::ReadUncommitted => 'READ UNCOMMITTED',
            self::ReadCommitted => 'READ COMMITTED',
            self::RepeatableRead => 'REPEATABLE READ',
            self::Serializable => 'SERIALIZABLE',
        };
    }
}
