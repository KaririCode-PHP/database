<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Unit;

use KaririCode\Database\Exception\QueryException;
use KaririCode\Database\ValueObject\ResultRow;
use PHPUnit\Framework\TestCase;

final class ResultRowTest extends TestCase
{
    public function testReadsTypedValues(): void
    {
        $row = new ResultRow(['name' => 'id', 'len' => '11', 'nullable' => 'f', 'def' => null]);
        self::assertSame('id', $row->string('name'));
        self::assertSame(11, $row->int('len'));
        self::assertFalse($row->bool('nullable'));
        self::assertNull($row->nullableString('def'));
        self::assertSame('', $row->string('def'), 'NULL num acessor nao-nulo vira vazio');
    }

    public function testMissingColumnFailsLoudly(): void
    {
        $row = new ResultRow(['name' => 'id']);
        foreach (['string', 'nullableString', 'int', 'nullableInt', 'bool'] as $accessor) {
            try {
                $row->{$accessor}('NAME');
                self::fail("{$accessor}: esperava QueryException");
            } catch (QueryException $e) {
                self::assertSame(6041, $e->getCode());
            }
        }
    }
}
