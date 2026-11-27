<?php

declare(strict_types=1);

namespace KaririCode\Database\Event;

use KaririCode\Database\Contract\DataStoreEvent;

final readonly class ConnectionError implements DataStoreEvent
{
    /**
     * @param array<int|string, mixed> $params
     */
    public function __construct(
        public \Throwable $exception,
        public ?string $sql = null,
        public array $params = [],
    ) {}
}
