<?php

declare(strict_types=1);

namespace KaririCode\Database\Driver;

use KaririCode\Database\Contract\Driver;
use KaririCode\Database\Contract\DriverConnection;
use KaririCode\Database\Driver\ExceptionConverter\PostgreSqlExceptionConverter;
use KaririCode\Database\Driver\Pdo\PdoConnection;
use KaririCode\Database\Exception\ConfigurationException;
use KaririCode\Database\ValueObject\ConnectionParams;

/**
 * Opens PostgreSQL sessions through pdo_pgsql (Pdo\Pgsql on PHP 8.4):
 * native prepares, exceptions and associative fetches, then sets the
 * client encoding. MySQL's names for UTF-8 (utf8mb4, the ConnectionParams
 * default, and utf8) are sent as UTF8, the only spelling PostgreSQL
 * accepts for it.
 *
 * Preconditions: host and database come from configuration; the charset
 * may hold only letters, digits, "-" and "_" (ConfigurationException
 * 6019); an empty charset keeps the server's client_encoding.
 *
 * Thread-safety: stateless; each connect() opens a new session.
 *
 * Side effects: opens a network connection and runs SET NAMES. libpq
 * reports every refused connection as 08006, so the server message tells
 * wrong credentials (6012) and an unknown database (6013) from the rest
 * (6010).
 *
 * Example:
 *
 *     $connection = (new PdoPgSqlDriver())->connect(new ConnectionParams(
 *         driver: 'pdo_pgsql', host: 'db', port: 5432, database: 'shop', username: 'app', password: $secret,
 *     ));
 *
 * References: ADR-001; ADR-004; SPEC-001 §3.
 *
 * @see https://www.postgresql.org/docs/current/multibyte.html
 */
final class PdoPgSqlDriver implements Driver
{
    #[\Override]
    public function connect(ConnectionParams $params): DriverConnection
    {
        if (preg_match('/^[a-zA-Z0-9_-]*$/', $params->charset) !== 1) {
            throw ConfigurationException::invalidCharset();
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $params->host,
            $params->port,
            $params->database,
        );

        // array_replace mantem as chaves inteiras dos atributos PDO: o spread (...)
        // renumerava as opcoes do usuario, que caiam em outros atributos.
        $options = array_replace([
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ], $params->driverOptions);
        $converter = new PostgreSqlExceptionConverter();

        try {
            $pdo = class_exists(\Pdo\Pgsql::class)
                ? new \Pdo\Pgsql(dsn: $dsn, username: $params->username, password: $params->password, options: $options)
                : new \PDO(dsn: $dsn, username: $params->username, password: $params->password, options: $options);

            if ($params->charset !== '') {
                $pdo->exec("SET NAMES '" . self::clientEncoding($params->charset) . "'");
            }

            return new PdoConnection($pdo, $converter);
        } catch (\PDOException $e) {
            throw $converter->convertConnectFailure($e);
        }
    }

    /** utf8mb4 e o padrao de ConnectionParams; o PostgreSQL recusa esse nome. */
    private static function clientEncoding(string $charset): string
    {
        return in_array(strtolower($charset), ['utf8mb4', 'utf8'], true) ? 'UTF8' : $charset;
    }
}
