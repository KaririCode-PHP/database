<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Integration\Sqlite;

use KaririCode\Database\Tests\Support\Sqlite;
use KaririCode\Database\Type\ScalarValue;
use PHPUnit\Framework\TestCase;

final class BulkInsertTest extends TestCase
{
    public function testChunksRowsByPlaceholderLimit(): void
    {
        $c = Sqlite::connection();
        $cols = array_map(fn(int $i): string => "c{$i}", range(1, 10));
        $c->executeStatement('CREATE TABLE wide (' . implode(' INTEGER, ', $cols) . ' INTEGER)');
        $rows = array_map(fn(int $r): array => array_combine($cols, array_fill(0, 10, $r)), range(1, 250));
        self::assertSame(250, $c->createQueryBuilder()->insert('wide')->bulkValues($rows)->executeStatement());
        self::assertSame(250, ScalarValue::asInt($c->executeQuery('SELECT COUNT(*) FROM wide')->fetchOne(), 'integer'));
    }

    /** BUG-001: mais colunas que o limite por bloco nao pode gerar array_chunk(…, 0). */
    public function testMoreColumnsThanTheChunkBudget(): void
    {
        $c = Sqlite::connection();
        $cols = array_map(fn(int $i): string => "k{$i}", range(1, 1000));
        $c->executeStatement('CREATE TABLE huge (' . implode(' INTEGER, ', $cols) . ' INTEGER)');
        $rows = [array_combine($cols, array_fill(0, 1000, 1)), array_combine($cols, array_fill(0, 1000, 2))];
        self::assertSame(2, $c->createQueryBuilder()->insert('huge')->bulkValues($rows)->executeStatement());
    }
}
