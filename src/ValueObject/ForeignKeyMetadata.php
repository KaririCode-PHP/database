<?php

declare(strict_types=1);

namespace KaririCode\Database\ValueObject;

use KaririCode\Database\Enum\ReferentialAction;

/**
 * One foreign key as a schema inspector read it: name, local columns, the
 * referenced table and columns, and the update and delete actions.
 *
 * Preconditions: local and foreign columns are in key order and pair up
 * by position.
 *
 * Thread-safety: immutable.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $key = $inspector->listTableForeignKeys('order_items')[0];
 *     $joins = array_combine($key->localColumns, $key->foreignColumns);
 *
 * References: ADR-008; SPEC-001 §2.1.
 *
 * @see https://www.postgresql.org/docs/current/ddl-constraints.html#DDL-CONSTRAINTS-FK
 */
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
