<?php

declare(strict_types=1);

namespace KaririCode\Database\ValueObject;

/**
 * The vendor and version of the connected server, detected at connect, so
 * a platform can switch features by version instead of by subclass:
 * RETURNING from MariaDB 10.5, bound-parameter limits from SQLite 3.32.
 *
 * Preconditions: vendor is mysql, mariadb, postgresql or sqlite ('unknown'
 * when the native handle is not PDO); gte() compares major, then minor.
 *
 * Thread-safety: immutable.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     if ($connection->platform->version->gte(8, 0)) {
 *         // MySQL 8 window functions
 *     }
 *
 * References: ADR-003 (version-aware platforms, GAP-DB-02); SPEC-001 §6.
 *
 * @see https://www.php.net/manual/en/pdo.getattribute.php
 */
final readonly class PlatformVersion
{
    public function __construct(
        public string $vendor,
        public int $major,
        public int $minor = 0,
        public int $patch = 0,
    ) {}

    public function isMySQL(): bool
    {
        return $this->vendor === 'mysql';
    }

    public function isMariaDb(): bool
    {
        return $this->vendor === 'mariadb';
    }

    public function isPostgreSql(): bool
    {
        return $this->vendor === 'postgresql';
    }

    public function isSqlite(): bool
    {
        return $this->vendor === 'sqlite';
    }

    public function gte(int $major, int $minor = 0): bool
    {
        if ($this->major > $major) {
            return true;
        }

        return $this->major === $major && $this->minor >= $minor;
    }

    public function __toString(): string
    {
        return "{$this->vendor} {$this->major}.{$this->minor}.{$this->patch}";
    }
}
