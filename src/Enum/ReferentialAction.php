<?php

declare(strict_types=1);

namespace KaririCode\Database\Enum;

// Backed: E-003 justified — values match SQL DDL literals.
enum ReferentialAction: string
{
    case Cascade = 'CASCADE';
    case SetNull = 'SET NULL';
    case SetDefault = 'SET DEFAULT';
    case Restrict = 'RESTRICT';
    case NoAction = 'NO ACTION';
}
