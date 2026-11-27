<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Support;

use KaririCode\Database\Contract\Connection;
use KaririCode\Database\Contract\Platform;
use KaririCode\Database\Enum\ParameterType;
use KaririCode\Database\Type\TypeRegistry;
use PHPUnit\Framework\Assert;

/**
 * Writes one value of each built-in type into a column declared by that
 * type for the platform, reads it back and compares: a wrong declaration,
 * a format the server rejects or one it returns differently fails here.
 */
trait TypeRoundTrip
{
    /** @return iterable<string, array{string, mixed}> */
    public static function builtInTypeSamples(): iterable
    {
        yield 'integer' => ['integer', 42];
        yield 'bigint' => ['bigint', '9007199254740993'];
        yield 'smallint' => ['smallint', 7];
        yield 'float' => ['float', 1.5];
        yield 'decimal' => ['decimal', '12.34'];
        yield 'string' => ['string', 'olá, mundo'];
        yield 'ascii_string' => ['ascii_string', 'abc-123'];
        yield 'text' => ['text', str_repeat('texto longo ', 500)];
        yield 'boolean true' => ['boolean', true];
        yield 'boolean false' => ['boolean', false];
        yield 'datetime' => ['datetime', new \DateTimeImmutable('2026-11-27 10:11:12')];
        yield 'datetimetz' => ['datetimetz', new \DateTimeImmutable('2026-11-27 10:11:12+02:00')];
        yield 'date' => ['date', new \DateTimeImmutable('2026-11-27')];
        yield 'time' => ['time', new \DateTimeImmutable('1970-01-01 10:11:12')];
        yield 'dateinterval' => ['dateinterval', new \DateInterval('P1Y2M3DT4H5M6S')];
        yield 'json' => ['json', ['a' => 1, 'b' => [true, null, 'ç']]];
        yield 'uuid' => ['uuid', '0190a5c4-7b7e-7cc1-9d3e-2f4b5a6c7d8e'];
        yield 'binary' => ['binary', "\x00\x01\xfe\xff"];
        yield 'blob' => ['blob', "\x00blob\xff" . str_repeat("\x7f", 300)];
    }

    private static function assertTypeRoundTrips(Connection $connection, Platform $platform, string $type, mixed $value): void
    {
        $converter = (new TypeRegistry())->get($type);
        $connection->executeStatement('DROP TABLE IF EXISTS kc_types');
        $connection->executeStatement('CREATE TABLE kc_types (v ' . $converter->getSqlDeclaration([], $platform) . ' NULL)');

        $connection->executeStatement(
            'INSERT INTO kc_types (v) VALUES (?)',
            [$converter->convertToDatabaseValue($value, $platform)],
            [self::bindingType($type)],
        );
        $read = $converter->convertToPhpValue($connection->executeQuery('SELECT v FROM kc_types')->fetchOne(), $platform);
        $connection->executeStatement('DROP TABLE kc_types');

        self::assertSameValue($value, $read);
    }

    private static function bindingType(string $type): int
    {
        return match ($type) {
            'binary', 'blob' => ParameterType::BINARY->value,
            'boolean' => ParameterType::BOOLEAN->value,
            'integer', 'smallint' => ParameterType::INTEGER->value,
            default => ParameterType::STRING->value,
        };
    }

    private static function assertSameValue(mixed $expected, mixed $actual): void
    {
        if ($expected instanceof \DateInterval) {
            Assert::assertInstanceOf(\DateInterval::class, $actual);
            Assert::assertSame(
                [$expected->y, $expected->m, $expected->d, $expected->h, $expected->i, $expected->s],
                [$actual->y, $actual->m, $actual->d, $actual->h, $actual->i, $actual->s],
            );

            return;
        }

        if ($expected instanceof \DateTimeImmutable) {
            Assert::assertInstanceOf(\DateTimeImmutable::class, $actual);
            Assert::assertSame($expected->format('Y-m-d H:i:s P'), $actual->setTimezone($expected->getTimezone())->format('Y-m-d H:i:s P'));

            return;
        }

        if (is_float($expected)) {
            Assert::assertIsFloat($actual);
            Assert::assertEqualsWithDelta($expected, $actual, 1e-9);

            return;
        }

        Assert::assertSame($expected, $actual);
    }
}
