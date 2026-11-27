<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Unit;

use KaririCode\Database\Exception\TypeConversionException;
use KaririCode\Database\Type\ScalarValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Conversao estrita: o que o driver pode devolver passa; o resto falha sem ecoar o valor. */
final class ScalarValueTest extends TestCase
{
    /** @return iterable<string, array{\Closure(): mixed, mixed}> */
    public static function accepted(): iterable
    {
        yield 'int de int' => [static fn(): mixed => ScalarValue::asInt(5, 'x'), 5];
        yield 'int de string' => [static fn(): mixed => ScalarValue::asInt(' -7 ', 'x'), -7];
        yield 'int de float inteiro' => [static fn(): mixed => ScalarValue::asInt(3.0, 'x'), 3];
        yield 'int de bool' => [static fn(): mixed => ScalarValue::asInt(true, 'x'), 1];
        yield 'float de string' => [static fn(): mixed => ScalarValue::asFloat('3.25', 'x'), 3.25];
        yield 'float em notacao cientifica' => [static fn(): mixed => ScalarValue::asFloat('1e3', 'x'), 1000.0];
        yield 'float de int' => [static fn(): mixed => ScalarValue::asFloat(2, 'x'), 2.0];
        yield 'bool t do PostgreSQL' => [static fn(): mixed => ScalarValue::asBool('t', 'x'), true];
        yield 'bool 0 do MySQL' => [static fn(): mixed => ScalarValue::asBool('0', 'x'), false];
        yield 'bool de int' => [static fn(): mixed => ScalarValue::asBool(1, 'x'), true];
        yield 'string de int' => [static fn(): mixed => ScalarValue::asString(12, 'x'), '12'];
        yield 'string de bool' => [static fn(): mixed => ScalarValue::asString(false, 'x'), '0'];
    }

    /** @return iterable<string, array{\Closure(): mixed}> */
    public static function rejected(): iterable
    {
        yield 'int de texto' => [static fn(): mixed => ScalarValue::asInt('s3cr3t', 'x')];
        yield 'int de decimal em string' => [static fn(): mixed => ScalarValue::asInt('12.5', 'x')];
        yield 'int de float fracionario' => [static fn(): mixed => ScalarValue::asInt(3.5, 'x')];
        yield 'float de texto' => [static fn(): mixed => ScalarValue::asFloat('s3cr3t', 'x')];
        yield 'bool ambiguo' => [static fn(): mixed => ScalarValue::asBool('s3cr3t', 'x')];
        yield 'bool de float' => [static fn(): mixed => ScalarValue::asBool(2.5, 'x')];
        yield 'string de array' => [static fn(): mixed => ScalarValue::asString(['s3cr3t'], 'x')];
        yield 'string de null' => [static fn(): mixed => ScalarValue::asString(null, 'x')];
    }

    #[DataProvider('accepted')]
    public function testAcceptsWhatDriversReturn(\Closure $convert, mixed $expected): void
    {
        self::assertSame($expected, $convert());
    }

    #[DataProvider('rejected')]
    public function testRejectsWithoutEchoingTheValue(\Closure $convert): void
    {
        try {
            $convert();
            self::fail('esperava TypeConversionException');
        } catch (TypeConversionException $e) {
            self::assertSame(6120, $e->getCode());
            self::assertStringNotContainsString('s3cr3t', $e->getMessage());
        }
    }
}
