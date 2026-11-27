<?php

declare(strict_types=1);

namespace KaririCode\Database\Enum;

/**
 * Portable column kinds, integer through UUID, for schema tooling built on
 * this package. The package itself names types by string in TypeRegistry
 * and takes no ColumnType.
 *
 * Preconditions: none.
 *
 * Thread-safety: an enum; immutable.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $typeName = match ($kind) {
 *         ColumnType::Json => 'json',
 *         ColumnType::Uuid => 'uuid',
 *         default => 'string',
 *     };
 *
 * References: SPEC-001 §2.4.
 *
 * @see https://www.php.net/manual/en/language.enumerations.php
 */
enum ColumnType
{
    case Integer;
    case BigInt;
    case SmallInt;
    case Float;
    case Decimal;
    case String;
    case Text;
    case Boolean;
    case Date;
    case DateTime;
    case Timestamp;
    case Time;
    case Binary;
    case Blob;
    case Json;
    case Uuid;
}
