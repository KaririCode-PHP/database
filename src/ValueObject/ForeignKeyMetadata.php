<?php

declare(strict_types=1);

namespace KaririCode\Database\ValueObject;

use KaririCode\Database\Enum\ReferentialAction;

final readonly class ForeignKeyMetadata
{
    /**
     * @param array<int, string> $localColumns
     * @param array<int, string> $foreignColumns
     */
    public function __construct(
        public string $name,
        public array $localColumns,
        public string $foreignTable,
        public array $foreignColumns,
        public ReferentialAction $onUpdate = ReferentialAction::NoAction,
        public ReferentialAction $onDelete = ReferentialAction::NoAction,
    ) {}
}
