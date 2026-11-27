<?php

declare(strict_types=1);

namespace KaririCode\Database\Enum;

/**
 * The PDO drivers the package opens, by the name ConnectionParams carries:
 * pdo_mysql (MySQL and MariaDB), pdo_pgsql and pdo_sqlite.
 *
 * Preconditions: ConnectionParams::driverType() maps the name and rejects
 * any other with ConfigurationException 6020.
 *
 * Thread-safety: an enum; immutable.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $driver = (new ConnectionParams(driver: 'pdo_pgsql'))->driverType();   // DriverType::PdoPgsql
 *
 * References: ADR-001; SPEC-001 §2.4.
 *
 * @see https://www.php.net/manual/en/pdo.drivers.php
 */
enum DriverType: string
{
    case PdoMysql = 'pdo_mysql';
    case PdoPgsql = 'pdo_pgsql';
    case PdoSqlite = 'pdo_sqlite';
}
