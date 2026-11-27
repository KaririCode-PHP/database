<?php

declare(strict_types=1);

namespace KaririCode\Database\Contract;

use KaririCode\Database\ValueObject\ColumnMetadata;
use KaririCode\Database\ValueObject\ForeignKeyMetadata;
use KaririCode\Database\ValueObject\IndexMetadata;

/**
 * Reads the catalog of a live database (tables, columns, indexes, foreign
 * keys) into read-only metadata, for migrations, tooling and schema checks.
 * It never changes the schema.
 *
 * Preconditions: the user can read the catalog (information_schema,
 * pg_catalog or PRAGMA); $schema defaults to the current database or
 * search path.
 *
 * Thread-safety: holds a connection; not safe to share.
 *
 * Side effects: runs catalog queries.
 *
 * Example:
 *
 *     if (!$inspector->tableExists('migrations')) {
 *         $connection->executeStatement('CREATE TABLE migrations (version VARCHAR(32) PRIMARY KEY)');
 *     }
 *
 * References: ADR-008 (read-only inspector); SPEC-001 §2.1.
 *
 * @see https://www.postgresql.org/docs/current/information-schema.html
 */
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
