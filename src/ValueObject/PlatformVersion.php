<?php

declare(strict_types=1);

namespace KaririCode\Database\ValueObject;

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
