<?php

declare(strict_types=1);

namespace KaririCode\Database\Driver;

use KaririCode\Database\Contract\Driver;
use KaririCode\Database\Contract\DriverConnection;
use KaririCode\Database\Driver\Pdo\PdoConnection;
use KaririCode\Database\Exception\ConnectionException;
use KaririCode\Database\ValueObject\ConnectionParams;

// ── PdoMySqlDriver ───────────────────────────────────────────────────
//
// Uses Pdo\Mysql (PHP 8.4) for typed native connection.
// Ref: Doctrine DBAL 4.4 — PHP 8.4 PDO subclasses support.
// ─────────────────────────────────────────────────────────────────────

final class PdoMySqlDriver implements Driver
{
    #[\Override]
    public function connect(ConnectionParams $params): DriverConnection
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $params->host,
            $params->port,
            $params->database,
            $params->charset,
        );

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_STRINGIFY_FETCHES => false,
            ...$params->driverOptions,
        ];

        try {
            $pdo = class_exists(\Pdo\Mysql::class)
                ? new \Pdo\Mysql(dsn: $dsn, username: $params->username, password: $params->password, options: $options)
                : new \PDO(dsn: $dsn, username: $params->username, password: $params->password, options: $options);

            return new PdoConnection($pdo);
        } catch (\PDOException $e) {
            throw ConnectionException::connectionFailed($e);
        }
    }
}
