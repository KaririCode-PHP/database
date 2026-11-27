<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Integration\Sqlite;

use KaririCode\Database\Enum\ReferentialAction;
use KaririCode\Database\Platform\SqlitePlatform;
use KaririCode\Database\Schema\SqliteSchemaInspector;
use KaririCode\Database\Tests\Support\Sqlite;
use KaririCode\Database\ValueObject\ColumnMetadata;
use KaririCode\Database\ValueObject\ForeignKeyMetadata;
use KaririCode\Database\ValueObject\IndexMetadata;
use KaririCode\Database\ValueObject\PlatformVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins SQLite identifier quoting, bulk-insert SQL, the version thresholds
 * of savepoints and bound parameters, and what the schema inspector reads
 * from PRAGMA output: nullability, auto-increment, index kinds and
 * composite foreign keys with their actions.
 */
#[CoversClass(SqlitePlatform::class)]
#[CoversClass(SqliteSchemaInspector::class)]
final class PlatformAndSchemaTest extends TestCase
{
    public function testIdentifiersDoubleTheirQuotes(): void
    {
        self::assertSame('"a""b"', self::platform(3, 45)->quoteIdentifier('a"b'));
    }

    public function testBulkInsertQuotesTableAndColumnsAndRepeatsTheRowPlaceholder(): void
    {
        self::assertSame(
            'INSERT INTO "t" ("id", "na""me") VALUES (?, ?), (?, ?), (?, ?)',
            self::platform(3, 45)->getBulkInsertSql('t', ['id', 'na"me'], 3),
        );
    }

    /** @return iterable<string, array{int, int, bool}> */
    public static function savepointVersions(): iterable
    {
        yield '2.9' => [2, 9, false];
        yield '3.5' => [3, 5, false];
        yield '3.6' => [3, 6, true];
        yield '4.0' => [4, 0, true];
    }

    #[DataProvider('savepointVersions')]
    public function testSavepointsNeedSqliteThreeSix(int $major, int $minor, bool $supported): void
    {
        self::assertSame($supported, self::platform($major, $minor)->supportsSavepoints());
    }

    /** @return iterable<string, array{int, int, int}> */
    public static function placeholderVersions(): iterable
    {
        yield '2.40' => [2, 40, 999];
        yield '3.31' => [3, 31, 999];
        yield '3.32' => [3, 32, 32766];
        yield '4.0' => [4, 0, 32766];
    }

    #[DataProvider('placeholderVersions')]
    public function testThePlaceholderLimitRisesInSqliteThreeThirtyTwo(int $major, int $minor, int $limit): void
    {
        self::assertSame($limit, self::platform($major, $minor)->maxPlaceholders());
    }

    public function testTheInspectorReadsColumnsIndexesAndForeignKeys(): void
    {
        $connection = Sqlite::connection();
        foreach ([
            'CREATE TABLE parent (id integer PRIMARY KEY, a TEXT NOT NULL, b TEXT, UNIQUE (a, b))',
            'CREATE TABLE codes (code TEXT PRIMARY KEY, n INTEGER)',
            'CREATE TABLE child (id INTEGER PRIMARY KEY, pa TEXT, pb TEXT, other_id INTEGER, '
                . 'FOREIGN KEY (pa, pb) REFERENCES parent (a, b) ON UPDATE CASCADE ON DELETE SET NULL, '
                . 'FOREIGN KEY (other_id) REFERENCES parent (id))',
            'CREATE INDEX child_pa ON child (pa)',
            'CREATE UNIQUE INDEX child_other ON child (other_id)',
        ] as $ddl) {
            $connection->executeStatement($ddl);
        }
        $inspector = new SqliteSchemaInspector($connection);

        $parent = self::byName($inspector->listTableColumns('parent'));
        self::assertTrue($parent['id']->autoIncrement, 'a lower-case integer primary key is the rowid');
        self::assertFalse($parent['a']->nullable);
        self::assertTrue($parent['b']->nullable);
        self::assertFalse($parent['a']->autoIncrement);

        $codes = self::byName($inspector->listTableColumns('codes'));
        self::assertFalse($codes['code']->autoIncrement, 'a TEXT primary key is not auto-increment');
        self::assertFalse($codes['n']->autoIncrement, 'an INTEGER column outside the key is not auto-increment');

        $childIndexes = self::byName($inspector->listTableIndexes('child'));
        self::assertSame(['child_other', 'child_pa'], self::sortedKeys($childIndexes));
        self::assertFalse($childIndexes['child_pa']->primary);
        self::assertFalse($childIndexes['child_pa']->unique);
        self::assertTrue($childIndexes['child_other']->unique);
        self::assertFalse($childIndexes['child_other']->primary);

        $parentIndexes = $inspector->listTableIndexes('parent');
        self::assertCount(1, $parentIndexes);
        self::assertTrue($parentIndexes[0]->primary, 'the inspector reports sqlite_autoindex_* as primary');

        $keys = $inspector->listTableForeignKeys('child');
        self::assertCount(2, $keys);
        $composite = self::onlyWithTwoColumns($keys);
        self::assertSame(['pa', 'pb'], $composite->localColumns);
        self::assertSame(['a', 'b'], $composite->foreignColumns);
        self::assertSame('parent', $composite->foreignTable);
        self::assertSame(ReferentialAction::Cascade, $composite->onUpdate);
        self::assertSame(ReferentialAction::SetNull, $composite->onDelete);
    }

    public function testDefaultsComeBackAsTheirValues(): void
    {
        $connection = Sqlite::connection();
        $connection->executeStatement("CREATE TABLE d (a TEXT DEFAULT 'open', b TEXT DEFAULT NULL, c INT DEFAULT 5, e TEXT DEFAULT 'it''s', f TEXT DEFAULT CURRENT_TIMESTAMP, g TEXT, h TEXT DEFAULT 'null')");

        $defaults = [];
        foreach ((new SqliteSchemaInspector($connection))->listTableColumns('d') as $column) {
            $defaults[$column->name] = $column->default;
        }

        self::assertSame(['a' => 'open', 'b' => null, 'c' => '5', 'e' => "it's", 'f' => 'CURRENT_TIMESTAMP', 'g' => null, 'h' => 'null'], $defaults);
    }

    private static function platform(int $major, int $minor): SqlitePlatform
    {
        return new SqlitePlatform(new PlatformVersion('sqlite', $major, $minor));
    }

    /**
     * @template T of ColumnMetadata|IndexMetadata
     * @param array<int, T> $items
     * @return array<string, T>
     */
    private static function byName(array $items): array
    {
        $named = [];
        foreach ($items as $item) {
            $named[$item->name] = $item;
        }

        return $named;
    }

    /**
     * @param array<string, mixed> $items
     * @return list<string>
     */
    private static function sortedKeys(array $items): array
    {
        $keys = array_keys($items);
        sort($keys);

        return $keys;
    }

    /** @param array<int, ForeignKeyMetadata> $keys */
    private static function onlyWithTwoColumns(array $keys): ForeignKeyMetadata
    {
        $matches = array_values(array_filter($keys, static fn(ForeignKeyMetadata $key): bool => count($key->localColumns) === 2));
        self::assertCount(1, $matches);

        return $matches[0];
    }
}
