<?php

declare(strict_types=1);

namespace KaririCode\Database\Event;

use KaririCode\Database\Contract\DataStoreEvent;

/**
 * A transaction or savepoint opened by DefaultConnection, with the nesting
 * level after it: 1 for the outer transaction, 2 or more for savepoints.
 *
 * Preconditions: none.
 *
 * Thread-safety: immutable.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     if ($event instanceof TransactionBegun && $event->nestingLevel === 1) {
 *         $unitOfWork->start();
 *     }
 *
 * References: ADR-005 (savepoints); ADR-013; SPEC-001 §2.3.
 *
 * @see https://www.php-fig.org/psr/psr-14/
 */
final readonly class TransactionBegun implements DataStoreEvent
{
    public function __construct(
        public int $nestingLevel,
    ) {}
}
