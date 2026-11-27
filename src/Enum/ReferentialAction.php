<?php

declare(strict_types=1);

namespace KaririCode\Database\Enum;

/**
 * What a foreign key does to referencing rows when the referenced row is
 * deleted or updated, as the schema inspectors read it from the catalog.
 *
 * Preconditions: the values are the SQL keywords; NoAction stands in when
 * a catalog reports none.
 *
 * Thread-safety: an enum; immutable.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $cascades = array_filter($inspector->listTableForeignKeys('order_items'), static fn(ForeignKeyMetadata $key): bool => $key->onDelete === ReferentialAction::Cascade);
 *
 * References: ADR-008; SPEC-001 §2.4.
 *
 * @see https://www.postgresql.org/docs/current/ddl-constraints.html#DDL-CONSTRAINTS-FK
 */
enum ReferentialAction: string
{
    case Cascade = 'CASCADE';
    case SetNull = 'SET NULL';
    case SetDefault = 'SET DEFAULT';
    case Restrict = 'RESTRICT';
    case NoAction = 'NO ACTION';
}
