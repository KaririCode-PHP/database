<?php

declare(strict_types=1);

namespace KaririCode\Database\Driver;

use KaririCode\Database\Contract\Driver;
use KaririCode\Database\Contract\DriverConnection;
use KaririCode\Database\Driver\Pdo\PdoConnection;
use KaririCode\Database\Exception\ConfigurationException;
use KaririCode\Database\Exception\ConnectionException;
use KaririCode\Database\ValueObject\ConnectionParams;

final class PdoPgSqlDriver implements Driver
{
    #[\Override]
    public function connect(ConnectionParams $params): DriverConnection
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $params->host,
            $params->port,
            $params->database,
        );

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            ...$params->driverOptions,
        ];

        try {
            $pdo = class_exists(\Pdo\Pgsql::class)
                ? new \Pdo\Pgsql(dsn: $dsn, username: $params->username, password: $params->password, options: $options)
                : new \PDO(dsn: $dsn, username: $params->username, password: $params->password, options: $options);

            if ($params->charset !== '') {
                // Validate charset: only alphanumeric, hyphen, underscore allowed
                if (!preg_match('/^[a-zA-Z0-9_-]+$/', $params->charset)) {
                    throw ConfigurationException::invalidCharset();
                }
                $pdo->exec("SET NAMES '{$params->charset}'");
            }

            return new PdoConnection($pdo);
        } catch (\PDOException $e) {
            throw ConnectionException::connectionFailed($e);
        }
    }
}
