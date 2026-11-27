<?php

declare(strict_types=1);

namespace KaririCode\Database\ValueObject;

final readonly class TableMetadata
{
    /**
     * @param array<int, ColumnMetadata> $columns
     * @param array<int, IndexMetadata> $indexes
     * @param array<int, ForeignKeyMetadata> $foreignKeys
     */
    public function __construct(
        public string $name,
        public array $columns,
        public array $indexes = [],
        public array $foreignKeys = [],
        public ?string $comment = null,
    ) {}
}
