<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Support;

use KaririCode\Database\Contract\Driver;
use KaririCode\Database\Contract\DriverConnection;
use KaririCode\Database\Driver\PdoSqliteDriver;
use KaririCode\Database\ValueObject\ConnectionParams;

/** Driver que falha N vezes antes de conectar; conta as tentativas. */
final class FlakyDriver implements Driver
{
    public int $calls = 0;

    public function __construct(private readonly int $failures, private readonly \Throwable $error) {}

    public function connect(ConnectionParams $params): DriverConnection
    {
        if ($this->calls++ < $this->failures) {
            throw $this->error;
        }

        return (new PdoSqliteDriver())->connect($params);
    }
}
