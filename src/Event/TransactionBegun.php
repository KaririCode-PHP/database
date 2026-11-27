<?php

declare(strict_types=1);

namespace KaririCode\Database\Event;

use KaririCode\Database\Contract\DataStoreEvent;

final readonly class TransactionBegun implements DataStoreEvent
{
    public function __construct(
        public int $nestingLevel,
    ) {}
}
