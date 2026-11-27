<?php

declare(strict_types=1);

namespace KaririCode\Database\Driver;

use KaririCode\Database\Contract\Driver;
use KaririCode\Database\Contract\DriverConnection;
use KaririCode\Database\Driver\Pdo\PdoConnection;
use KaririCode\Database\Exception\ConnectionException;
use KaririCode\Database\ValueObject\ConnectionParams;

final class PdoSqliteDriver implements Driver
{
    #[\Override]
    public function connect(ConnectionParams $params): DriverConnection
    {
        $path = $params->database ?: ':memory:';
        $dsn = "sqlite:{$path}";

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ...$params->driverOptions,
        ];

        try {
            $pdo = class_exists(\Pdo\Sqlite::class)
                ? new \Pdo\Sqlite($dsn, options: $options)
                : new \PDO($dsn, options: $options);

            $pdo->exec('PRAGMA journal_mode=WAL');
            $pdo->exec('PRAGMA foreign_keys=ON');

            return new PdoConnection($pdo);
        } catch (\PDOException $e) {
            throw ConnectionException::connectionFailed($e);
        }
    }
}
