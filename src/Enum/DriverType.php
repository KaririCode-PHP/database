<?php

declare(strict_types=1);

namespace KaririCode\Database\Enum;

// Backed: E-003 justified — values match DSN/config string literals.
enum DriverType: string
{
    case PdoMysql = 'pdo_mysql';
    case PdoPgsql = 'pdo_pgsql';
    case PdoSqlite = 'pdo_sqlite';
}
