<?php

declare(strict_types=1);

namespace KaririCode\Database\ValueObject;

final readonly class ColumnMetadata
{
    public function __construct(
        public string $name,
        public string $type,
        public ?int $length = null,
        public bool $nullable = false,
        public ?string $default = null,
        public bool $autoIncrement = false,
        public ?string $comment = null,
    ) {}
}
