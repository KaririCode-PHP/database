<?php

declare(strict_types=1);

namespace KaririCode\Database\Contract;

use KaririCode\Database\ValueObject\ConnectionParams;

/**
 * Opens a driver-level session from ConnectionParams. There is one
 * implementation per PHP database extension, and it is the unit a
 * Middleware decorates.
 *
 * Preconditions: the params name the driver the implementation serves.
 *
 * Thread-safety: implementations are stateless.
 *
 * Side effects: connect() opens a session; a refused connection arrives as
 * ConfigurationException or ConnectionException.
 *
 * Example:
 *
 *     $session = (new PdoPgSqlDriver())->connect($params);
 *
 * References: ADR-001 (two layers); ADR-002 (middleware); SPEC-001 §3.
 *
 * @see https://www.php.net/manual/en/book.pdo.php
 */
interface Driver
{
    public function connect(ConnectionParams $params): DriverConnection;
}
