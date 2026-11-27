<?php

declare(strict_types=1);

namespace KaririCode\Database\ValueObject;

/**
 * A table with its columns, indexes and foreign keys, for callers that
 * gather the inspector's lists into one value (a schema diff, a
 * migration generator). No inspector method returns it.
 *
 * Preconditions: the lists belong to the named table.
 *
 * Thread-safety: immutable.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $table = new TableMetadata(
 *         name: 'users',
 *         columns: $inspector->listTableColumns('users'),
 *         indexes: $inspector->listTableIndexes('users'),
 *         foreignKeys: $inspector->listTableForeignKeys('users'),
 *     );
 *
 * References: ADR-008; SPEC-001 §2.1.
 *
 * @see https://www.postgresql.org/docs/current/information-schema.html
 */
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
