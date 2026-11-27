<?php

declare(strict_types=1);

namespace KaririCode\Database\Event;

use KaririCode\Database\Contract\DataStoreEvent;

/**
 * A commit or a savepoint release by DefaultConnection, with the nesting
 * level after it: 0 when the outer transaction committed.
 *
 * Preconditions: none.
 *
 * Thread-safety: immutable.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     if ($event instanceof TransactionCommitted && $event->nestingLevel === 0) {
 *         $outbox->flush();
 *     }
 *
 * References: ADR-005; ADR-013; SPEC-001 §2.3.
 *
 * @see https://www.php-fig.org/psr/psr-14/
 */
final readonly class TransactionCommitted implements DataStoreEvent
{
    public function __construct(
        public int $nestingLevel,
    ) {}
}
