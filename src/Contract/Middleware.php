<?php

declare(strict_types=1);

namespace KaririCode\Database\Contract;

interface Middleware
{
    public function wrap(Driver $driver): Driver;
}
