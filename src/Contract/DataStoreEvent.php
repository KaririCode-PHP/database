<?php

declare(strict_types=1);

namespace KaririCode\Database\Contract;

/**
 * Marker shared with kariricode/nosql: every connection, query and
 * transaction event implements it, so one listener observes SQL and NoSQL
 * stores alike.
 *
 * Preconditions: none.
 *
 * Thread-safety: not applicable; the interface has no members.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     if ($event instanceof DataStoreEvent) {
 *         $metrics->increment($event::class);
 *     }
 *
 * References: ADR-013 (connection events); SPEC-001 §2.3 and §8.
 *
 * @see https://www.php-fig.org/psr/psr-14/
 */
interface DataStoreEvent {}
