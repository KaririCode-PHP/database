<?php

declare(strict_types=1);

namespace KaririCode\Database\Driver;

use KaririCode\Database\Contract\Driver;
use KaririCode\Database\Contract\DriverConnection;
use KaririCode\Database\Driver\ExceptionConverter\MySqlExceptionConverter;
use KaririCode\Database\Driver\Pdo\PdoConnection;
use KaririCode\Database\Exception\ConfigurationException;
use KaririCode\Database\ValueObject\ConnectionParams;

/**
 * Opens MySQL and MariaDB sessions through pdo_mysql (Pdo\Mysql on PHP
 * 8.4): native prepares, exceptions, associative fetches and native
 * scalar types, with the user's driverOptions replacing those defaults
 * key by key.
 *
 * Preconditions: host, database and charset come from configuration, not
 * from request input; the charset may hold only letters, digits, "-" and
 * "_" (ConfigurationException 6019), since it is part of the DSN.
 *
 * Thread-safety: stateless; each connect() opens a new session.
 *
 * Side effects: opens a network connection. A refused connection becomes
 * a package exception: 1045 and 1044 are credentials (6012), 1049 an
 * unknown database (6013), anything else connectionFailed (6010).
 *
 * Example:
 *
 *     $connection = (new PdoMySqlDriver())->connect(new ConnectionParams(
 *         driver: 'pdo_mysql', host: 'db', database: 'shop', username: 'app', password: $secret,
 *     ));
 *
 * References: ADR-001; ADR-004; SPEC-001 §3.
 *
 * @see https://www.php.net/manual/en/ref.pdo-mysql.connection.php
 */
final class PdoMySqlDriver implements Driver
{
    #[\Override]
    public function connect(ConnectionParams $params): DriverConnection
    {
        if (preg_match('/^[a-zA-Z0-9_-]*$/', $params->charset) !== 1) {
            throw ConfigurationException::invalidCharset();
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $params->host,
            $params->port,
            $params->database,
            $params->charset,
        );

        // array_replace mantem as chaves inteiras dos atributos PDO: o spread (...)
        // renumerava as opcoes do usuario, que caiam em outros atributos.
        $options = array_replace([
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_STRINGIFY_FETCHES => false,
        ], $params->driverOptions);
        $converter = new MySqlExceptionConverter();

        try {
            $pdo = class_exists(\Pdo\Mysql::class)
                ? new \Pdo\Mysql(dsn: $dsn, username: $params->username, password: $params->password, options: $options)
                : new \PDO(dsn: $dsn, username: $params->username, password: $params->password, options: $options);

            return new PdoConnection($pdo, $converter);
        } catch (\PDOException $e) {
            throw $converter->convertConnectFailure($e);
        }
    }
}
