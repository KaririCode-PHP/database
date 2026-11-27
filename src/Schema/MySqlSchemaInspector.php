<?php

declare(strict_types=1);

namespace KaririCode\Database\Schema;

use KaririCode\Database\Contract\Connection;
use KaririCode\Database\Contract\SchemaInspector;
use KaririCode\Database\Enum\ReferentialAction;
use KaririCode\Database\Exception\QueryException;
use KaririCode\Database\Exception\SchemaException;
use KaririCode\Database\Type\ScalarValue;
use KaririCode\Database\ValueObject\ColumnMetadata;
use KaririCode\Database\ValueObject\ForeignKeyMetadata;
use KaririCode\Database\ValueObject\IndexMetadata;
use KaririCode\Database\ValueObject\ResultRow;

/**
 * Reads tables, columns, indexes and foreign keys of a MySQL or MariaDB
 * database from information_schema. MariaDB 10.2.7+ quotes literal
 * defaults and reports a missing default as NULL text; both come back as
 * MySQL reports them, so a default reads the same on either server.
 *
 * Preconditions: $schema is a database name and defaults to DATABASE();
 * the user needs to see the tables in information_schema.
 *
 * Thread-safety: holds a connection; not safe to share.
 *
 * Side effects: runs catalog queries.
 *
 * Example:
 *
 *     $inspector = new MySqlSchemaInspector($connection);
 *     $columns = $inspector->listTableColumns('orders');
 *
 * References: ADR-008 (read-only inspector); SPEC-001 §2.1.
 *
 * @see https://dev.mysql.com/doc/refman/8.4/en/information-schema-columns-table.html
 */
final class MySqlSchemaInspector implements SchemaInspector
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    #[\Override]
    public function listTableNames(?string $schema = null): array
    {
        $database = $schema ?? $this->getCurrentDatabase();
        $result = $this->connection->executeQuery(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME",
            [$database],
        );

        return array_map(static fn(mixed $name): string => ScalarValue::asString($name, 'string'), $result->fetchFirstColumn());
    }

    #[\Override]
    public function listTableColumns(string $table, ?string $schema = null): array
    {
        $database = $schema ?? $this->getCurrentDatabase();
        $result = $this->connection->executeQuery(
            'SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLUMN_COMMENT
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION',
            [$database, $table],
        );

        $columns = [];
        foreach ($result->iterateAssociative() as $values) {
            $row = new ResultRow($values);
            $columns[] = new ColumnMetadata(
                name: $row->string('COLUMN_NAME'),
                type: $row->string('DATA_TYPE'),
                length: $row->nullableInt('CHARACTER_MAXIMUM_LENGTH'),
                nullable: $row->string('IS_NULLABLE') === 'YES',
                default: $this->columnDefault($row->nullableString('COLUMN_DEFAULT')),
                autoIncrement: str_contains($row->string('EXTRA'), 'auto_increment'),
                comment: self::nonEmpty($row->nullableString('COLUMN_COMMENT')),
            );
        }

        return $columns;
    }

    #[\Override]
    public function listTableIndexes(string $table, ?string $schema = null): array
    {
        $database = $schema ?? $this->getCurrentDatabase();
        $result = $this->connection->executeQuery(
            'SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [$database, $table],
        );

        $grouped = [];
        foreach ($result->iterateAssociative() as $values) {
            $row = new ResultRow($values);
            $name = $row->string('INDEX_NAME');
            // NON_UNIQUE chega como int com prepares nativos: comparar com '0' dava sempre falso.
            $grouped[$name] ??= ['name' => $name, 'columns' => [], 'unique' => $row->int('NON_UNIQUE') === 0, 'primary' => $name === 'PRIMARY'];
            $grouped[$name]['columns'][] = $row->string('COLUMN_NAME');
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
        $database = $schema ?? $this->getCurrentDatabase();
        $result = $this->connection->executeQuery(
            'SELECT kcu.CONSTRAINT_NAME, kcu.COLUMN_NAME, kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME,
                    rc.UPDATE_RULE, rc.DELETE_RULE
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
             JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
               ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME AND rc.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA
             WHERE kcu.TABLE_SCHEMA = ? AND kcu.TABLE_NAME = ? AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
             ORDER BY kcu.CONSTRAINT_NAME, kcu.ORDINAL_POSITION',
            [$database, $table],
        );

        $grouped = [];
        foreach ($result->iterateAssociative() as $values) {
            $row = new ResultRow($values);
            $name = $row->string('CONSTRAINT_NAME');
            $grouped[$name] ??= ['name' => $name, 'localColumns' => [], 'foreignTable' => $row->string('REFERENCED_TABLE_NAME'), 'foreignColumns' => [], 'onUpdate' => $row->string('UPDATE_RULE'), 'onDelete' => $row->string('DELETE_RULE')];
            $grouped[$name]['localColumns'][] = $row->string('COLUMN_NAME');
            $grouped[$name]['foreignColumns'][] = $row->string('REFERENCED_COLUMN_NAME');
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
        $database = $schema ?? $this->getCurrentDatabase();
        $result = $this->connection->executeQuery(
            "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1",
            [$database, $table],
        );

        return $result->fetchOne() !== false;
    }

    private function getCurrentDatabase(): string
    {
        $result = $this->connection->executeQuery('SELECT DATABASE()');
        return self::databaseName($result->fetchOne());
    }

    private static function nonEmpty(?string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /** DATABASE() devolve NULL quando a conexao nao tem banco selecionado. */
    private static function databaseName(mixed $name): string
    {
        return $name === false || $name === null ? throw QueryException::introspectionFailed() : ScalarValue::asString($name, 'string');
    }

    /**
     * MariaDB 10.2.7+ quotes literal defaults and reports a nullable column
     * without default as the string NULL, to tell them from expressions;
     * MySQL reports both bare. Both come back here as MySQL reports them.
     */
    private function columnDefault(?string $default): ?string
    {
        if ($default === null || !$this->quotesDefaults()) {
            return $default;
        }

        if ($default === 'NULL') {
            return null;
        }

        return preg_match("/\\A'(.*)'\\z/s", $default, $literal) === 1 ? str_replace("''", "'", $literal[1]) : $default;
    }

    /** Todas as versoes suportadas do MariaDB (10.4+) citam os defaults; o MySQL nunca. */
    private function quotesDefaults(): bool
    {
        return $this->connection->platform->version->isMariaDb();
    }
}
