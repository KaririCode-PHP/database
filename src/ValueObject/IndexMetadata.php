<?php

declare(strict_types=1);

namespace KaririCode\Database\ValueObject;

/**
 * One index as a schema inspector read it: name, columns in index order,
 * and whether it is unique or the primary key.
 *
 * Preconditions: a primary key is also unique. SQLite reports the index
 * behind a PRIMARY KEY or UNIQUE constraint (sqlite_autoindex_*) as
 * primary.
 *
 * Thread-safety: immutable.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $unique = array_filter($inspector->listTableIndexes('users'), static fn(IndexMetadata $index): bool => $index->unique);
 *
 * References: ADR-008; SPEC-001 §2.1.
 *
 * @see https://www.sqlite.org/fileformat2.html#intschema
 */
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
