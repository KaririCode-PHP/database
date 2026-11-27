<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Integration\Sqlite;

use KaririCode\Database\Enum\UnionType;
use KaririCode\Database\Tests\Support\Sqlite;
use KaririCode\Database\Type\ScalarValue;
use PHPUnit\Framework\TestCase;

final class QueryBuilderTest extends TestCase
{
    private \KaririCode\Database\Connection\DefaultConnection $c;

    protected function setUp(): void
    {
        $this->c = Sqlite::connection();
        $this->c->executeStatement('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT)');
        foreach ([[1, 'a'], [2, 'b'], [3, 'c']] as [$id, $name]) {
            $this->c->insert('t', ['id' => $id, 'name' => $name]);
        }
    }

    public function testDistinct(): void
    {
        $this->c->insert('t', ['id' => 4, 'name' => 'a']);
        self::assertCount(3, $this->c->createQueryBuilder()->select('name')->distinct()->from('t')->executeQuery()->fetchAllAssociative());
    }

    public function testUnionAllThenUnion(): void
    {
        $r = $this->c->createQueryBuilder()->union('SELECT 1 AS x')->addUnion('SELECT 1', UnionType::All)->addUnion('SELECT 2')->executeQuery()->fetchFirstColumn();
        sort($r);
        self::assertSame([1, 2], array_map(static fn(mixed $v): int => ScalarValue::asInt($v, 'integer'), $r));
    }

    public function testRecursiveCte(): void
    {
        $qb = $this->c->createQueryBuilder()->with('nums', 'SELECT 1 AS n UNION ALL SELECT n + 1 FROM nums WHERE n < 5', true)->select('n')->from('nums');
        self::assertSame([1, 2, 3, 4, 5], array_map(static fn(mixed $v): int => ScalarValue::asInt($v, 'integer'), $qb->executeQuery()->fetchFirstColumn()));
    }

    public function testNamedParameter(): void
    {
        $qb = $this->c->createQueryBuilder();
        $qb->select('name')->from('t')->where('id = ' . $qb->createNamedParameter(2));
        self::assertSame('b', $qb->executeQuery()->fetchOne());
    }

    /** DB-17: chaves posicionais sao 0-based (Doctrine DBAL 4.2, QueryBuilder l. 1463). */
    public function testPositionalParameters(): void
    {
        $qb = $this->c->createQueryBuilder();
        $qb->select('COUNT(*)')->from('t')->where('id >= ' . $qb->createPositionalParameter(2))->andWhere('id <= ' . $qb->createPositionalParameter(3));
        self::assertSame(2, ScalarValue::asInt($qb->executeQuery()->fetchOne(), 'integer'));
    }

    public function testKeyValueAndIndexedFetches(): void
    {
        $kv = $this->c->executeQuery('SELECT id, name FROM t ORDER BY id')->fetchAllKeyValue();
        $ix = $this->c->executeQuery('SELECT id, name FROM t ORDER BY id')->fetchAllAssociativeIndexed();
        $it = iterator_to_array($this->c->executeQuery('SELECT id, name FROM t ORDER BY id')->iterateKeyValue());
        self::assertSame([1 => 'a', 2 => 'b', 3 => 'c'], $kv);
        self::assertSame(['name' => 'b'], $ix[2]);
        self::assertSame($kv, $it);
    }

    public function testConvenienceDml(): void
    {
        self::assertSame(1, $this->c->update('t', ['name' => 'z'], ['id' => 3]));
        self::assertSame('z', $this->c->executeQuery('SELECT name FROM t WHERE id = 3')->fetchOne());
        self::assertSame(1, $this->c->delete('t', ['id' => 3]));
    }
}
