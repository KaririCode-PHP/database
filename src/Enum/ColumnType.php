<?php

declare(strict_types=1);

namespace KaririCode\Database\Enum;

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
