<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Integration\Sqlite;

use KaririCode\Database\Tests\Support\Sqlite;
use KaririCode\Database\Tests\Support\TypeRoundTrip;
use KaririCode\Database\Type\TypeRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Every built-in type through a real SQLite column and back. */
#[CoversClass(TypeRegistry::class)]
final class TypeRoundTripTest extends TestCase
{
    use TypeRoundTrip;

    #[DataProvider('builtInTypeSamples')]
    public function testBuiltInTypeRoundTrips(string $type, mixed $value): void
    {
        $connection = Sqlite::connection();

        self::assertTypeRoundTrips($connection, $connection->platform, $type, $value);
    }
}
