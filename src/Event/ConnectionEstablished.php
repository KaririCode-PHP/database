<?php

declare(strict_types=1);

namespace KaririCode\Database\Event;

use KaririCode\Database\Contract\DataStoreEvent;

/**
 * A session opened by ConnectionFactory: the registry name of the
 * connection, the driver and how long the connect took, in milliseconds.
 *
 * Preconditions: none.
 *
 * Thread-safety: immutable.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $histogram->observe($event->durationMs, ['connection' => $event->connectionName]);
 *
 * References: ADR-013; SPEC-001 §2.3.
 *
 * @see https://www.php-fig.org/psr/psr-14/
 */
final readonly class ConnectionEstablished implements DataStoreEvent
{
    public function __construct(
        public string $connectionName,
        public string $driverType,
        public float $durationMs,
    ) {}
}
