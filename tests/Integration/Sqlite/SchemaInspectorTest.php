<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Integration\Sqlite;

use KaririCode\Database\Enum\ReferentialAction;
use KaririCode\Database\Schema\SqliteSchemaInspector;
use KaririCode\Database\Tests\Support\Sqlite;
use PHPUnit\Framework\TestCase;

/** Exercita ResultRow estrito sobre a saida real dos pragma_*() do SQLite. */
final class SchemaInspectorTest extends TestCase
{
    private SqliteSchemaInspector $inspector;

    protected function setUp(): void
    {
        $c = Sqlite::connection();
        $c->executeStatement('CREATE TABLE parent (id INTEGER PRIMARY KEY)');
        $c->executeStatement("CREATE TABLE child (id INTEGER PRIMARY KEY, parent_id INTEGER NOT NULL REFERENCES parent(id) ON DELETE CASCADE, name VARCHAR(40) DEFAULT 'x')");
        $c->executeStatement('CREATE UNIQUE INDEX child_name_uq ON child(name)');
        $this->inspector = new SqliteSchemaInspector($c);
    }

    public function testTablesAndColumns(): void
    {
        self::assertSame(['child', 'parent'], $this->inspector->listTableNames());
        self::assertTrue($this->inspector->tableExists('child'));
        self::assertFalse($this->inspector->tableExists('nope'));
        $columns = $this->inspector->listTableColumns('child');
        self::assertSame(['id', 'parent_id', 'name'], array_map(fn($c) => $c->name, $columns));
        self::assertFalse($columns[1]->nullable);
        self::assertStringContainsString('x', (string) $columns[2]->default);
    }

    public function testIndexesAndForeignKeys(): void
    {
        $unique = array_values(array_filter($this->inspector->listTableIndexes('child'), fn($i) => $i->columns === ['name']));
        self::assertCount(1, $unique);
        self::assertTrue($unique[0]->unique);
        $fks = $this->inspector->listTableForeignKeys('child');
        self::assertCount(1, $fks);
        self::assertSame(['parent_id'], $fks[0]->localColumns);
        self::assertSame('parent', $fks[0]->foreignTable);
        self::assertSame(['id'], $fks[0]->foreignColumns);
        self::assertSame(ReferentialAction::Cascade, $fks[0]->onDelete);
    }

    public function testInjectionAttemptIsJustAnUnknownTable(): void
    {
        self::assertSame([], $this->inspector->listTableColumns("child); DROP TABLE parent; --"));
        self::assertTrue($this->inspector->tableExists('parent'));
    }
}
