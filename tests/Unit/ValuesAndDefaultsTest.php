<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Unit;

use KaririCode\Database\Cache\CachedResult;
use KaririCode\Database\Cache\CacheProfile;
use KaririCode\Database\DsnParser;
use KaririCode\Database\Exception\ConfigurationException;
use KaririCode\Database\Exception\ConnectionException;
use KaririCode\Database\Exception\TypeConversionException;
use KaririCode\Database\Type\ScalarValue;
use KaririCode\Database\ValueObject\ColumnMetadata;
use KaririCode\Database\ValueObject\ConnectionParams;
use KaririCode\Database\ValueObject\IndexMetadata;
use KaririCode\Database\ValueObject\ResultRow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins scalar conversion, typed row access, cache keys and cached results,
 * the defaults of the value objects and the codes of the exceptions.
 */
#[CoversClass(ScalarValue::class)]
#[CoversClass(ResultRow::class)]
#[CoversClass(CacheProfile::class)]
#[CoversClass(CachedResult::class)]
#[CoversClass(ColumnMetadata::class)]
#[CoversClass(IndexMetadata::class)]
#[CoversClass(ConnectionParams::class)]
#[CoversClass(ConnectionException::class)]
#[CoversClass(ConfigurationException::class)]
#[CoversClass(DsnParser::class)]
final class ValuesAndDefaultsTest extends TestCase
{
    /** @return iterable<string, array{\Closure(): mixed, mixed}> */
    public static function conversions(): iterable
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'from-stringable';
            }
        };

        yield 'float as string' => [static fn(): string => ScalarValue::asString(1.5, 'string'), '1.5'];
        yield 'stringable as string' => [static fn(): string => ScalarValue::asString($stringable, 'string'), 'from-stringable'];
        yield 'false as int' => [static fn(): int => ScalarValue::asInt(false, 'integer'), 0];
        yield 'true as int' => [static fn(): int => ScalarValue::asInt(true, 'integer'), 1];
        yield 'integral float as int' => [static fn(): int => ScalarValue::asInt(2.0, 'integer'), 2];
        yield 'float as float' => [static fn(): float => ScalarValue::asFloat(1.5, 'float'), 1.5];
        yield 'true as bool' => [static fn(): bool => ScalarValue::asBool(true, 'boolean'), true];
        yield 'false as bool' => [static fn(): bool => ScalarValue::asBool(false, 'boolean'), false];
        yield 'minus one as bool' => [static fn(): bool => ScalarValue::asBool(-1, 'boolean'), true];
        yield 'zero as bool' => [static fn(): bool => ScalarValue::asBool(0, 'boolean'), false];
        yield 'upper-case TRUE' => [static fn(): bool => ScalarValue::asBool('TRUE', 'boolean'), true];
        yield 'upper-case OFF' => [static fn(): bool => ScalarValue::asBool('OFF', 'boolean'), false];
        yield 'int key' => [static fn(): int|string => ScalarValue::asKey(5), 5];
        yield 'string key' => [static fn(): int|string => ScalarValue::asKey('k'), 'k'];
        yield 'float key' => [static fn(): int|string => ScalarValue::asKey(1.5), '1.5'];
        yield 'bool key' => [static fn(): int|string => ScalarValue::asKey(true), '1'];
    }

    #[DataProvider('conversions')]
    public function testConvertsScalarsTheWayDriversReturnThem(\Closure $convert, mixed $expected): void
    {
        self::assertSame($expected, $convert());
    }

    public function testAFractionalFloatIsNotAnInt(): void
    {
        $this->expectException(TypeConversionException::class);

        ScalarValue::asInt(2.5, 'integer');
    }

    public function testRowsReadNullColumnsAsTheTypeDefault(): void
    {
        $row = new ResultRow(['n' => null, 'v' => '7', 'flag' => null]);

        self::assertSame(0, $row->int('n'));
        self::assertNull($row->nullableInt('n'));
        self::assertSame(7, $row->nullableInt('v'));
        self::assertFalse($row->bool('flag'));
    }

    public function testCacheProfileDefaultsAndKeyCoverTheSql(): void
    {
        $profile = new CacheProfile();

        self::assertSame(0, $profile->ttl);
        self::assertNotSame(
            $profile->generateCacheKey('SELECT 1', [], []),
            $profile->generateCacheKey('SELECT 2', [], []),
        );
        self::assertSame('fixed', (new CacheProfile(key: 'fixed'))->generateCacheKey('SELECT 1', [], []));
    }

    public function testCachedResultReturnsOnlyTheRowsNotYetFetched(): void
    {
        $result = new CachedResult([['id' => 1], ['id' => 2], ['id' => 3]]);

        self::assertSame(['id' => 1], $result->fetchAssociative());
        self::assertSame([['id' => 2], ['id' => 3]], $result->fetchAllAssociative());
        self::assertSame([], $result->fetchAllAssociative());
    }

    public function testValueObjectsDefaultToFalseFlagsAndTheMySqlPort(): void
    {
        $column = new ColumnMetadata('id', 'INTEGER');
        $index = new IndexMetadata('idx', ['id']);

        self::assertFalse($column->nullable);
        self::assertFalse($column->autoIncrement);
        self::assertFalse($index->unique);
        self::assertFalse($index->primary);
        self::assertSame(3306, (new ConnectionParams(driver: 'pdo_mysql'))->port);
    }

    /** @return iterable<string, array{\Closure(\Throwable): \Throwable, int}> */
    public static function factories(): iterable
    {
        yield 'connection failed' => [static fn(\Throwable $p): \Throwable => ConnectionException::connectionFailed($p), 6010];
        yield 'connection lost' => [static fn(\Throwable $p): \Throwable => ConnectionException::connectionLost($p), 6011];
        yield 'credentials rejected' => [static fn(\Throwable $p): \Throwable => ConfigurationException::authenticationFailed($p), 6012];
    }

    /** @param \Closure(\Throwable): \Throwable $factory */
    #[DataProvider('factories')]
    public function testExceptionsCarryTheirCodeTagAndCause(\Closure $factory, int $code): void
    {
        $cause = new \RuntimeException('driver detail');
        $exception = $factory($cause);

        self::assertSame($code, $exception->getCode());
        self::assertStringStartsWith("[DB_{$code}] ", $exception->getMessage());
        self::assertSame($cause, $exception->getPrevious());
    }

    public function testADsnWithoutSchemeIsInvalid(): void
    {
        try {
            (new DsnParser())->parse('//db.example/shop');
            self::fail('expected an invalid DSN');
        } catch (ConfigurationException $e) {
            self::assertSame(6016, $e->getCode());
        }
    }

    public function testTheSchemeIsCaseInsensitive(): void
    {
        self::assertSame('pdo_mysql', (new DsnParser())->parse('MySQL://app@db.example/shop')->driver);
    }
}
