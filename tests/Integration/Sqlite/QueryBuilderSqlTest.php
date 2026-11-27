<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Integration\Sqlite;

use KaririCode\Database\Connection\DefaultConnection;
use KaririCode\Database\Enum\ParameterType;
use KaririCode\Database\Enum\UnionType;
use KaririCode\Database\Exception\QueryException;
use KaririCode\Database\Middleware\LoggingMiddleware;
use KaririCode\Database\Query\SqlQueryBuilder;
use KaririCode\Database\Tests\Support\RecordingLogger;
use KaririCode\Database\Tests\Support\Sqlite;
use KaririCode\Database\Type\ScalarValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Pins the exact SQL the builder writes for each clause, the parameters it
 * keeps, and how a bulk insert splits rows by the platform's placeholder
 * limit.
 */
#[CoversClass(SqlQueryBuilder::class)]
final class QueryBuilderSqlTest extends TestCase
{
    private DefaultConnection $connection;

    private RecordingLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
        $this->connection = Sqlite::connection([new LoggingMiddleware($this->logger)]);
    }

    public function testSelectWithoutColumnsSelectsEverything(): void
    {
        self::assertSame('SELECT * FROM t', $this->builder()->select()->from('t')->sql());
    }

    public function testAliasesConnectorsAndPagination(): void
    {
        $sql = $this->builder()
            ->select('x.id')
            ->from('t', 'x')
            ->where('x.a = 1')
            ->andWhere('x.b = 2')
            ->orWhere('x.c = 3')
            ->setMaxResults(2)
            ->setFirstResult(3)
            ->sql();

        self::assertSame('SELECT x.id FROM t x WHERE x.a = 1 AND x.b = 2 OR x.c = 3 LIMIT 2 OFFSET 3', $sql);
    }

    public function testUnionKeepsItsFirstQueryAndNamesEachKind(): void
    {
        self::assertSame('SELECT 1', $this->builder()->union('SELECT 1')->sql());

        $sql = $this->builder()
            ->union('SELECT 1')
            ->addUnion('SELECT 2', UnionType::All)
            ->addUnion('SELECT 3')
            ->setMaxResults(5)
            ->setFirstResult(1)
            ->sql();

        self::assertSame('SELECT 1 UNION ALL SELECT 2 UNION SELECT 3 LIMIT 5 OFFSET 1', $sql);
    }

    public function testCommonTableExpressions(): void
    {
        self::assertSame(
            'WITH c AS (SELECT 1 AS n) SELECT n FROM c',
            $this->builder()->with('c', 'SELECT 1 AS n')->select('n')->from('c')->sql(),
        );
        self::assertSame(
            'WITH RECURSIVE a AS (SELECT 1), b AS (SELECT 2) SELECT * FROM b',
            $this->builder()->with('a', 'SELECT 1', true)->with('b', 'SELECT 2')->select()->from('b')->sql(),
        );
    }

    public function testInsertUpdateAndDeleteSql(): void
    {
        self::assertSame('INSERT INTO t (id, name) VALUES (?, ?)', $this->builder()->insert('t')->values(['id' => 1, 'name' => 'a'])->sql());
        self::assertSame('UPDATE t u SET name = :n WHERE u.id = 1', $this->builder()->update('t', 'u')->set('name', ':n')->where('u.id = 1')->sql());
        self::assertSame('DELETE FROM t WHERE id = 1', $this->builder()->delete('t')->where('id = 1')->sql());
    }

    public function testInsertBindsItsValues(): void
    {
        $this->connection->executeStatement('CREATE TABLE t (id INTEGER, name TEXT)');

        $affected = $this->builder()->insert('t')->values(['id' => 9, 'name' => 'z'])->executeStatement();

        self::assertSame(1, $affected);
        self::assertSame([['id' => 9, 'name' => 'z']], $this->connection->executeQuery('SELECT id, name FROM t')->fetchAllAssociative());
    }

    public function testParametersKeepTheirTypesOnlyWhenGiven(): void
    {
        $builder = $this->builder();
        $first = $builder->createNamedParameter('a');
        $second = $builder->createNamedParameter('b', ParameterType::INTEGER->value);

        self::assertSame([':dcValue1', ':dcValue2'], [$first, $second]);
        self::assertSame([':dcValue1' => 'a', ':dcValue2' => 'b'], $builder->parameters());
        self::assertSame([':dcValue2' => ParameterType::INTEGER->value], $builder->parameterTypes());
    }

    public function testBulkInsertWritesEveryColumnOfEveryRow(): void
    {
        $this->connection->executeStatement('CREATE TABLE p (id INTEGER, name TEXT)');

        self::assertSame(1, $this->builder()->insert('p')->bulkValues([['id' => 1, 'name' => 'one']])->executeStatement());
        self::assertSame(2, $this->builder()->insert('p')->bulkValues([['id' => 2, 'name' => 'two'], ['id' => 3]])->executeStatement());

        self::assertSame(
            [['id' => 1, 'name' => 'one'], ['id' => 2, 'name' => 'two'], ['id' => 3, 'name' => null]],
            $this->connection->executeQuery('SELECT id, name FROM p ORDER BY id')->fetchAllAssociative(),
        );
    }

    public function testBulkInsertSplitsByThePlaceholderLimit(): void
    {
        $limit = $this->connection->platform->maxPlaceholders();
        $this->connection->executeStatement('CREATE TABLE three (a INTEGER, b INTEGER, c INTEGER)');
        $this->connection->executeStatement('CREATE TABLE four (a INTEGER, b INTEGER, c INTEGER, d INTEGER)');
        $this->connection->executeStatement('CREATE TABLE one (a INTEGER)');

        $perChunk = intdiv($limit, 3);
        $rows = $perChunk + 5;
        $affected = $this->builder()->insert('three')->bulkValues(self::rows($rows, ['a', 'b', 'c']))->executeStatement();
        self::assertSame($rows, $affected, 'the count adds every chunk');
        self::assertSame(2, $this->insertStatements('three'));
        self::assertSame($rows, $this->rowCount('three'));

        // 4 columns do not divide the limit evenly: rounding up would overflow it.
        $fourRows = intdiv($limit, 4) + 1;
        $this->builder()->insert('four')->bulkValues(self::rows($fourRows, ['a', 'b', 'c', 'd']))->executeStatement();
        self::assertSame(2, $this->insertStatements('four'));

        // One column: a whole chunk holds as many rows as the limit allows.
        $this->builder()->insert('one')->bulkValues(self::rows($limit, ['a']))->executeStatement();
        self::assertSame(1, $this->insertStatements('one'));
        self::assertSame($limit, $this->rowCount('one'));
    }

    public function testAQueryWithoutAKindIsAnInvalidState(): void
    {
        $builder = $this->builder()->union('SELECT 1');
        (new \ReflectionProperty(SqlQueryBuilder::class, 'type'))->setValue($builder, 'MERGE');

        $this->expectException(QueryException::class);
        $this->expectExceptionCode(6040);
        $builder->sql();
    }

    private function builder(): SqlQueryBuilder
    {
        $builder = $this->connection->createQueryBuilder();
        self::assertInstanceOf(SqlQueryBuilder::class, $builder);

        return $builder;
    }

    /**
     * @param list<string> $columns
     * @return list<array<string, int>>
     */
    private static function rows(int $count, array $columns): array
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = array_fill_keys($columns, $i);
        }

        return $rows;
    }

    private function insertStatements(string $table): int
    {
        $count = 0;
        foreach ($this->logger->records as [, $message, $context]) {
            $sql = $message === 'Statement executed.' ? ($context['sql'] ?? null) : null;
            if (is_string($sql) && str_starts_with($sql, "INSERT INTO \"{$table}\"")) {
                $count++;
            }
        }

        return $count;
    }

    private function rowCount(string $table): int
    {
        return ScalarValue::asInt($this->connection->executeQuery("SELECT COUNT(*) FROM {$table}")->fetchOne(), 'integer');
    }
}
