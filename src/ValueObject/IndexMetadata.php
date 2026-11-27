<?php

declare(strict_types=1);

namespace KaririCode\Database\ValueObject;

final readonly class IndexMetadata
{
    /** @param array<int, string> $columns */
    public function __construct(
        public string $name,
        public array $columns,
        public bool $unique = false,
        public bool $primary = false,
    ) {}
}
