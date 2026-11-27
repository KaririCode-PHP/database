<?php

declare(strict_types=1);

namespace KaririCode\Database\Enum;

/**
 * Whether QueryBuilder::addUnion() keeps duplicate rows (UNION ALL) or
 * removes them (UNION).
 *
 * Preconditions: none.
 *
 * Thread-safety: an enum; immutable.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $qb->union('SELECT id FROM archived')->addUnion('SELECT id FROM active', UnionType::All);
 *
 * References: ADR-009; SPEC-001 §5.
 *
 * @see https://www.postgresql.org/docs/current/queries-union.html
 */
enum UnionType
{
    case Distinct;
    case All;
}
