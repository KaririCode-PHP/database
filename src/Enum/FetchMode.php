<?php

declare(strict_types=1);

namespace KaririCode\Database\Enum;

enum FetchMode
{
    case Associative;
    case Numeric;
    case FirstColumn;
    case KeyValue;
}
