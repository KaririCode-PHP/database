<?php

declare(strict_types=1);

namespace KaririCode\Database\Enum;

/**
 * The four shapes a Result returns rows in, for callers that pick the
 * shape at run time, such as an export taking it as a parameter. Result
 * has one method per shape and takes no FetchMode.
 *
 * Preconditions: none.
 *
 * Thread-safety: an enum; immutable.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $rows = match ($mode) {
 *         FetchMode::Associative => $result->fetchAllAssociative(),
 *         FetchMode::Numeric => $result->fetchAllNumeric(),
 *         FetchMode::FirstColumn => $result->fetchFirstColumn(),
 *         FetchMode::KeyValue => $result->fetchAllKeyValue(),
 *     };
 *
 * References: SPEC-001 §2.4.
 *
 * @see https://www.php.net/manual/en/pdostatement.fetch.php
 */
enum FetchMode
{
    case Associative;
    case Numeric;
    case FirstColumn;
    case KeyValue;
}
