<?php

declare(strict_types=1);

namespace KaririCode\Database\Event;

use KaririCode\Database\Contract\DataStoreEvent;

final readonly class ConnectionEstablished implements DataStoreEvent
{
    public function __construct(
        public string $connectionName,
        public string $driverType,
        public float $durationMs,
    ) {}
}
