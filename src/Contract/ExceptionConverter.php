<?php

declare(strict_types=1);

namespace KaririCode\Database\Contract;

use KaririCode\Database\Exception\DatabaseException;

interface ExceptionConverter
{
    public function convert(\Throwable $exception, ?string $sql = null): DatabaseException;
}
