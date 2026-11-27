<?php

declare(strict_types=1);

namespace KaririCode\Database\Schema;

use KaririCode\Database\Contract\Connection;
use KaririCode\Database\Contract\SchemaInspector;
use KaririCode\Database\Enum\ReferentialAction;
use KaririCode\Database\ValueObject\ColumnMetadata;
use KaririCode\Database\ValueObject\ForeignKeyMetadata;
use KaririCode\Database\ValueObject\IndexMetadata;
use KaririCode\Database\Type\ScalarValue;
use KaririCode\Database\ValueObject\ResultRow;

final class SqliteSchemaInspector implements SchemaInspector
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    #[\Override]
    public function listTableNames(?string $schema = null): array
    {
        $result = $this->connection->executeQuery(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name",
        );

        return array_map(static fn(mixed $name): string => ScalarValue::asString($name, 'string'), $result->fetchFirstColumn());
    }

    #[\Override]
    public function listTableColumns(string $table, ?string $schema = null): array
    {
        // pragma_*() aceita parametro ligado; PRAGMA table_info({$table}) interpolava o nome.
        $result = $this->connection->executeQuery(sql: 'SELECT * FROM pragma_table_info(?)', params: [$table]);
        $columns = [];

        foreach ($result->iterateAssociative() as $values) {
            $row = new ResultRow($values);
            $columns[] = new ColumnMetadata(
                name: $row->string('name'),
                type: $row->string('type'),
                nullable: $row->int('notnull') === 0,
                default: $row->nullableString('dflt_value'),
                autoIncrement: $row->int('pk') > 0 && strtoupper($row->string('type')) === 'INTEGER',
            );
        }

        return $columns;
    }

    #[\Override]
    public function listTableIndexes(string $table, ?string $schema = null): array
    {
        $result = $this->connection->executeQuery(sql: 'SELECT * FROM pragma_index_list(?)', params: [$table]);
        $indexes = [];

        foreach ($result->iterateAssociative() as $values) {
            $index = new ResultRow($values);
            $name = $index->string('name');
            $columns = [];

            foreach ($this->connection->executeQuery(sql: 'SELECT name FROM pragma_index_info(?)', params: [$name])->iterateAssociative() as $column) {
                $columns[] = (new ResultRow($column))->string('name');
            }

            $indexes[] = new IndexMetadata(
                name: $name,
                columns: $columns,
                unique: $index->int('unique') === 1,
                primary: $index->string('origin') === 'pk' || str_starts_with($name, 'sqlite_autoindex_'),
            );
        }

        return $indexes;
    }

    #[\Override]
    public function listTableForeignKeys(string $table, ?string $schema = null): array
    {
        $result = $this->connection->executeQuery(sql: 'SELECT * FROM pragma_foreign_key_list(?)', params: [$table]);
        $grouped = [];

        foreach ($result->iterateAssociative() as $values) {
            $row = new ResultRow($values);
            $id = $row->int('id');
            $grouped[$id] ??= ['localColumns' => [], 'foreignTable' => $row->string('table'), 'foreignColumns' => [], 'onUpdate' => $row->string('on_update'), 'onDelete' => $row->string('on_delete')];
            $grouped[$id]['localColumns'][] = $row->string('from');
            $grouped[$id]['foreignColumns'][] = $row->string('to');
        }

        $keys = [];
        foreach ($grouped as $id => $data) {
            $keys[] = new ForeignKeyMetadata(
                name: "fk_{$table}_{$id}",
                localColumns: $data['localColumns'],
                foreignTable: $data['foreignTable'],
                foreignColumns: $data['foreignColumns'],
                onUpdate: ReferentialAction::tryFrom($data['onUpdate']) ?? ReferentialAction::NoAction,
                onDelete: ReferentialAction::tryFrom($data['onDelete']) ?? ReferentialAction::NoAction,
            );
        }

        return $keys;
    }

    #[\Override]
    public function tableExists(string $table, ?string $schema = null): bool
    {
        $result = $this->connection->executeQuery(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1",
            [$table],
        );

        return $result->fetchOne() !== false;
    }
}
