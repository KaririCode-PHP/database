<?php

declare(strict_types=1);

namespace KaririCode\Database\ValueObject;

/**
 * One column as a schema inspector read it from the catalog: name, vendor
 * type name, length, nullability, default, auto-increment and comment.
 *
 * Preconditions: the type is the vendor's own name (varchar, integer,
 * character varying); the default is a literal without quotes or an
 * expression as the server wrote it, and null when there is none.
 *
 * Thread-safety: immutable.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     foreach ($inspector->listTableColumns('users') as $column) {
 *         echo $column->name, $column->nullable ? ' NULL' : ' NOT NULL', PHP_EOL;
 *     }
 *
 * References: ADR-008; SPEC-001 §2.1.
 *
 * @see https://www.postgresql.org/docs/current/infoschema-columns.html
 */
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
