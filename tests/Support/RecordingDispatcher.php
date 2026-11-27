<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Support;

use Psr\EventDispatcher\EventDispatcherInterface;

final class RecordingDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }
}
