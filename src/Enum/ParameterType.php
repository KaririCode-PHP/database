<?php

declare(strict_types=1);

namespace KaririCode\Database\Enum;

// Backed: E-003 justified — values match PDO::PARAM_* constants.
enum ParameterType: int
{
    case NULL = 0;
    case INTEGER = 1;
    case STRING = 2;
    case BINARY = 3;
    case BOOLEAN = 5;
    case ASCII = 17;
}
