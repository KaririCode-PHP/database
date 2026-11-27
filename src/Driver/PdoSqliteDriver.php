<?php

declare(strict_types=1);

namespace KaririCode\Database\Driver;

use KaririCode\Database\Contract\Driver;
use KaririCode\Database\Contract\DriverConnection;
use KaririCode\Database\Driver\ExceptionConverter\SqliteExceptionConverter;
use KaririCode\Database\Driver\Pdo\PdoConnection;
use KaririCode\Database\ValueObject\ConnectionParams;

/**
 * Opens SQLite databases through pdo_sqlite (Pdo\Sqlite on PHP 8.4): the
 * file named by the database parameter, or an in-memory database when it
 * is empty. Foreign keys are switched on, which SQLite leaves off by
 * default, and a file database uses write-ahead logging so readers do not
 * block the writer.
 *
 * Preconditions: the directory of a file database exists and is writable;
 * host, port and credentials are ignored.
 *
 * Thread-safety: stateless; each connect() opens a new handle, and two
 * handles on ":memory:" are two separate databases.
 *
 * Side effects: creates the file when it does not exist and switches it
 * to WAL, which adds the -wal and -shm files beside it. A file that cannot
 * be opened is connectionFailed (6010).
 *
 * Example:
 *
 *     $connection = (new PdoSqliteDriver())->connect(new ConnectionParams(driver: 'pdo_sqlite', database: '/var/lib/app/app.db'));
 *
 * References: ADR-001; ADR-004; SPEC-001 §3.
 *
 * @see https://www.sqlite.org/wal.html
 */
final class PdoSqliteDriver implements Driver
{
    #[\Override]
    public function connect(ConnectionParams $params): DriverConnection
    {
        $path = $params->database ?: ':memory:';
        $dsn = "sqlite:{$path}";

        // array_replace mantem as chaves inteiras dos atributos PDO: o spread (...)
        // renumerava as opcoes do usuario, que caiam em outros atributos.
        $options = array_replace([
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ], $params->driverOptions);
        $converter = new SqliteExceptionConverter();

        try {
            $pdo = class_exists(\Pdo\Sqlite::class)
                ? new \Pdo\Sqlite($dsn, options: $options)
                : new \PDO($dsn, options: $options);

            $pdo->exec('PRAGMA journal_mode=WAL');
            $pdo->exec('PRAGMA foreign_keys=ON');

            return new PdoConnection($pdo, $converter);
        } catch (\PDOException $e) {
            throw $converter->convertConnectFailure($e);
        }
    }
}
