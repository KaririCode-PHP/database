<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Support;

use KaririCode\Database\Connection\ConnectionFactory;
use KaririCode\Database\Connection\DefaultConnection;
use KaririCode\Database\ValueObject\ConnectionParams;
use Psr\EventDispatcher\EventDispatcherInterface;

final class Sqlite
{
    /** @param list<\KaririCode\Database\Contract\Middleware> $middlewares */
    public static function connection(array $middlewares = [], ?EventDispatcherInterface $dispatcher = null): DefaultConnection
    {
        return (new ConnectionFactory($middlewares, $dispatcher))->createConnection(new ConnectionParams(driver: 'pdo_sqlite', database: ':memory:'));
    }
}
