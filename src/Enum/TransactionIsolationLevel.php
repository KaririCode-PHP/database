<?php

declare(strict_types=1);

namespace KaririCode\Database\Enum;

// Backed: E-003 justified — SQL standard numeric isolation levels.
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
