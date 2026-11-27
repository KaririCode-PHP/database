<?php

declare(strict_types=1);

namespace KaririCode\Database\Event;

use KaririCode\Database\Contract\DataStoreEvent;

/**
 * A rollback by DefaultConnection, with the nesting level after it and
 * whether only a savepoint was rolled back.
 *
 * Preconditions: none.
 *
 * Thread-safety: immutable.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     if ($event instanceof TransactionRolledBack && !$event->toSavepoint) {
 *         $outbox->discard();
 *     }
 *
 * References: ADR-005; ADR-013; SPEC-001 §2.3.
 *
 * @see https://www.php-fig.org/psr/psr-14/
 */
final readonly class TransactionRolledBack implements DataStoreEvent
{
    public function __construct(
        public int $nestingLevel,
        public bool $toSavepoint,
    ) {}
}
