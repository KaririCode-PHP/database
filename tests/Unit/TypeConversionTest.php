<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Unit;

use KaririCode\Database\Exception\TypeConversionException;
use KaririCode\Database\Tests\Support\Sqlite;
use KaririCode\Database\Type\BooleanType;
use KaririCode\Database\Type\FloatType;
use KaririCode\Database\Type\IntegerType;
use PHPUnit\Framework\TestCase;

/** 4.4.0: os tipos deixam de coagir lixo para 0 ((int) 'abc'); falham com 6120. */
final class TypeConversionTest extends TestCase
{
    public function testValidValuesConvert(): void
    {
        $platform = Sqlite::connection()->platform;
        self::assertSame(42, (new IntegerType())->convertToPhpValue('42', $platform));
        self::assertSame(2.5, (new FloatType())->convertToPhpValue('2.5', $platform));
        self::assertTrue((new BooleanType())->convertToPhpValue('1', $platform));
        self::assertNull((new IntegerType())->convertToPhpValue(null, $platform));
    }

    public function testGarbageNoLongerBecomesZero(): void
    {
        $platform = Sqlite::connection()->platform;
        foreach ([new IntegerType(), new FloatType()] as $type) {
            try {
                $type->convertToPhpValue('abc', $platform);
                self::fail($type::class . ': esperava TypeConversionException');
            } catch (TypeConversionException $e) {
                self::assertSame(6120, $e->getCode());
            }
        }
    }
}
