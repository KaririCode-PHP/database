<?php

declare(strict_types=1);

namespace KaririCode\Database\Schema;

use KaririCode\Database\Contract\Connection;
use KaririCode\Database\Contract\SchemaInspector;
use KaririCode\Database\Enum\ReferentialAction;
use KaririCode\Database\Type\ScalarValue;
use KaririCode\Database\ValueObject\ColumnMetadata;
use KaririCode\Database\ValueObject\ForeignKeyMetadata;
use KaririCode\Database\ValueObject\IndexMetadata;
use KaririCode\Database\ValueObject\ResultRow;

final class PostgreSqlSchemaInspector implements SchemaInspector
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    #[\Override]
    public function listTableNames(?string $schema = null): array
    {
        $schemaName = $schema ?? 'public';
        $result = $this->connection->executeQuery(
            "SELECT tablename FROM pg_tables WHERE schemaname = ? ORDER BY tablename",
            [$schemaName],
        );

        return array_map(static fn(mixed $name): string => ScalarValue::asString($name, 'string'), $result->fetchFirstColumn());
    }

    #[\Override]
    public function listTableColumns(string $table, ?string $schema = null): array
    {
        $schemaName = $schema ?? 'public';
        $result = $this->connection->executeQuery(
            "SELECT column_name, data_type, character_maximum_length, is_nullable,
                    column_default, is_identity,
                    col_description((quote_ident(?) || '.' || quote_ident(?))::regclass, ordinal_position) as comment
             FROM information_schema.columns
             WHERE table_schema = ? AND table_name = ?
             ORDER BY ordinal_position",
            [$schemaName, $table, $schemaName, $table],
        );

        $columns = [];
        foreach ($result->iterateAssociative() as $values) {
            $row = new ResultRow($values);
            $default = $row->nullableString('column_default');
            $columns[] = new ColumnMetadata(
                name: $row->string('column_name'),
                type: $row->string('data_type'),
                length: $row->nullableInt('character_maximum_length'),
                nullable: $row->string('is_nullable') === 'YES',
                default: $default,
                autoIncrement: str_contains($default ?? '', 'nextval') || $row->string('is_identity') === 'YES',
                comment: $row->nullableString('comment'),
            );
        }

        return $columns;
    }

    #[\Override]
    public function listTableIndexes(string $table, ?string $schema = null): array
    {
        $schemaName = $schema ?? 'public';
        $result = $this->connection->executeQuery(
            "SELECT i.relname AS index_name, a.attname AS column_name,
                    ix.indisunique AS is_unique, ix.indisprimary AS is_primary
             FROM pg_index ix
             JOIN pg_class t ON t.oid = ix.indrelid
             JOIN pg_class i ON i.oid = ix.indexrelid
             JOIN pg_namespace n ON n.oid = t.relnamespace
             JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(ix.indkey)
             WHERE n.nspname = ? AND t.relname = ?
             ORDER BY i.relname, a.attnum",
            [$schemaName, $table],
        );

        $grouped = [];
        foreach ($result->iterateAssociative() as $values) {
            $row = new ResultRow($values);
            $name = $row->string('index_name');
            $grouped[$name] ??= ['name' => $name, 'columns' => [], 'unique' => $row->bool('is_unique'), 'primary' => $row->bool('is_primary')];
            $grouped[$name]['columns'][] = $row->string('column_name');
        }

        $indexes = [];
        foreach ($grouped as $data) {
            $indexes[] = new IndexMetadata(name: $data['name'], columns: $data['columns'], unique: $data['unique'], primary: $data['primary']);
        }

        return $indexes;
    }

    #[\Override]
    public function listTableForeignKeys(string $table, ?string $schema = null): array
    {
        $schemaName = $schema ?? 'public';
        $result = $this->connection->executeQuery(
            "SELECT tc.constraint_name, kcu.column_name, ccu.table_name AS foreign_table,
                    ccu.column_name AS foreign_column, rc.update_rule, rc.delete_rule
             FROM information_schema.table_constraints tc
             JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name
                AND tc.table_schema = kcu.table_schema
             JOIN information_schema.constraint_column_usage ccu ON tc.constraint_name = ccu.constraint_name
                AND tc.table_schema = ccu.table_schema
             JOIN information_schema.referential_constraints rc ON tc.constraint_name = rc.constraint_name
                AND tc.table_schema = rc.constraint_schema
             WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_schema = ? AND tc.table_name = ?
             ORDER BY tc.constraint_name, kcu.ordinal_position",
            [$schemaName, $table],
        );

        $grouped = [];
        foreach ($result->iterateAssociative() as $values) {
            $row = new ResultRow($values);
            $name = $row->string('constraint_name');
            $grouped[$name] ??= ['name' => $name, 'localColumns' => [], 'foreignTable' => $row->string('foreign_table'), 'foreignColumns' => [], 'onUpdate' => $row->string('update_rule'), 'onDelete' => $row->string('delete_rule')];
            $grouped[$name]['localColumns'][] = $row->string('column_name');
            $grouped[$name]['foreignColumns'][] = $row->string('foreign_column');
        }

        $keys = [];
        foreach ($grouped as $data) {
            $keys[] = new ForeignKeyMetadata(
                name: $data['name'],
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
        $schemaName = $schema ?? 'public';
        $result = $this->connection->executeQuery(
            "SELECT 1 FROM pg_tables WHERE schemaname = ? AND tablename = ? LIMIT 1",
            [$schemaName, $table],
        );

        return $result->fetchOne() !== false;
    }
}
