<?php

declare(strict_types=1);

namespace KaririCode\Database\Contract;

use KaririCode\Database\ValueObject\ColumnMetadata;
use KaririCode\Database\ValueObject\ForeignKeyMetadata;
use KaririCode\Database\ValueObject\IndexMetadata;

interface SchemaInspector
{
    /** @return array<int, string> */
    public function listTableNames(?string $schema = null): array;

    /** @return array<int, ColumnMetadata> */
    public function listTableColumns(string $table, ?string $schema = null): array;

    /** @return array<int, IndexMetadata> */
    public function listTableIndexes(string $table, ?string $schema = null): array;

    /** @return array<int, ForeignKeyMetadata> */
    public function listTableForeignKeys(string $table, ?string $schema = null): array;

    public function tableExists(string $table, ?string $schema = null): bool;
}
