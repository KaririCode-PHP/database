<?php

declare(strict_types=1);

namespace KaririCode\Database\Enum;

/**
 * How a bound value travels to the driver: NULL, integer, string, binary
 * (a LOB), boolean or ASCII string. PdoStatement maps each to a
 * PDO::PARAM_* constant; the values are what the $types arrays of
 * Connection hold.
 *
 * Preconditions: ASCII binds as a string; an unknown value binds as a
 * string too.
 *
 * Thread-safety: an enum; immutable.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $connection->executeQuery('SELECT * FROM files WHERE id = ?', [$id], [ParameterType::INTEGER->value]);
 *
 * References: SPEC-001 §2.4.
 *
 * @see https://www.php.net/manual/en/pdo.constants.php
 */
enum ParameterType: int
{
    case NULL = 0;
    case INTEGER = 1;
    case STRING = 2;
    case BINARY = 3;
    case BOOLEAN = 5;
    case ASCII = 17;
}
