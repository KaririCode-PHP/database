<?php

declare(strict_types=1);

namespace KaririCode\Database\Query;

use KaririCode\Database\Contract\Connection;
use KaririCode\Database\Contract\QueryBuilder;
use KaririCode\Database\Contract\Result;
use KaririCode\Database\Enum\UnionType;
use KaririCode\Database\Exception\QueryException;

// ── SqlQueryBuilder ──────────────────────────────────────────────────
//
// Entity-agnostic fluent SQL builder. Manipulates SQL strings and
// parameter bindings only — zero entity awareness.
//
// ORM extends this with entity-aware features (hydration, relations).
// DataFlow uses it directly for ETL query construction.
//
// Ref: Doctrine DBAL 4.4 QueryBuilder lives in DBAL, not ORM.
// ─────────────────────────────────────────────────────────────────────

final class SqlQueryBuilder implements QueryBuilder
{
    private string $type = 'SELECT';
    private string $table = '';
    private ?string $tableAlias = null;
    private bool $isDistinct = false;

    /** @var array<int, string> */
    private array $selectColumns = [];

    /** @var array<int, array{type: string, table: string, alias: string, condition: string}> */
    private array $joins = [];

    /** @var array<int, array{predicate: string, connector: string}> */
    private array $wheres = [];

    /** @var array<int, string> */
    private array $groupByColumns = [];

    /** @var array<int, array{predicate: string, connector: string}> */
    private array $havings = [];

    /** @var array<int, array{column: string, direction: string}> */
    private array $orderBys = [];

    private ?int $maxResults = null;
    private int $firstResult = 0;

    /** @var array<string, string> */
    private array $setClauses = [];

    /** @var array<string, mixed> */
    private array $insertValues = [];

    /** @var array<int, array<string, mixed>> */
    private array $bulkRows = [];

    /** @var array<int|string, mixed> */
    private array $parameters = [];

    /** @var array<int|string, int> */
    private array $parameterTypes = [];

    /** @var array<int, array{query: string, type: UnionType}> */
    private array $unions = [];

    /** @var array<int, array{name: string, query: string, recursive: bool}> */
    private array $ctes = [];

    private int $boundCounter = 0;

    public function __construct(
        private readonly Connection $connection,
    ) {}

    // ── SELECT ───────────────────────────────────────────────────────────

    #[\Override]
    public function select(string ...$columns): static
    {
        $this->type = 'SELECT';
        $this->selectColumns = $columns !== [] ? array_values($columns) : ['*'];

        return $this;
    }

    #[\Override]
    public function distinct(bool $flag = true): static
    {
        $this->isDistinct = $flag;

        return $this;
    }

    #[\Override]
    public function from(string $table, ?string $alias = null): static
    {
        $this->table = $table;
        $this->tableAlias = $alias;

        return $this;
    }

    // ── INSERT ───────────────────────────────────────────────────────────

    #[\Override]
    public function insert(string $table): static
    {
        $this->type = 'INSERT';
        $this->table = $table;

        return $this;
    }

    /**
     * @param array<string, mixed> $values
     */
    #[\Override]
    public function values(array $values): static
    {
        $this->insertValues = $values;

        return $this;
    }

    #[\Override]
    public function bulkValues(array $rows): static
    {
        $this->bulkRows = $rows;

        return $this;
    }

    // ── UPDATE ───────────────────────────────────────────────────────────

    #[\Override]
    public function update(string $table, ?string $alias = null): static
    {
        $this->type = 'UPDATE';
        $this->table = $table;
        $this->tableAlias = $alias;

        return $this;
    }

    #[\Override]
    public function set(string $column, string $value): static
    {
        $this->setClauses[$column] = $value;

        return $this;
    }

    // ── DELETE ───────────────────────────────────────────────────────────

    #[\Override]
    public function delete(string $table, ?string $alias = null): static
    {
        $this->type = 'DELETE';
        $this->table = $table;
        $this->tableAlias = $alias;

        return $this;
    }

    // ── WHERE ────────────────────────────────────────────────────────────

    #[\Override]
    public function where(string $predicate): static
    {
        $this->wheres = [['predicate' => $predicate, 'connector' => '']];

        return $this;
    }

    #[\Override]
    public function andWhere(string $predicate): static
    {
        $this->wheres[] = ['predicate' => $predicate, 'connector' => 'AND'];

        return $this;
    }

    #[\Override]
    public function orWhere(string $predicate): static
    {
        $this->wheres[] = ['predicate' => $predicate, 'connector' => 'OR'];

        return $this;
    }

    // ── JOIN ─────────────────────────────────────────────────────────────

    #[\Override]
    public function innerJoin(string $fromAlias, string $table, string $alias, string $condition): static
    {
        $this->joins[] = ['type' => 'INNER JOIN', 'table' => $table, 'alias' => $alias, 'condition' => $condition];

        return $this;
    }

    #[\Override]
    public function leftJoin(string $fromAlias, string $table, string $alias, string $condition): static
    {
        $this->joins[] = ['type' => 'LEFT JOIN', 'table' => $table, 'alias' => $alias, 'condition' => $condition];

        return $this;
    }

    #[\Override]
    public function rightJoin(string $fromAlias, string $table, string $alias, string $condition): static
    {
        $this->joins[] = ['type' => 'RIGHT JOIN', 'table' => $table, 'alias' => $alias, 'condition' => $condition];

        return $this;
    }

    // ── GROUP BY / HAVING ────────────────────────────────────────────────

    #[\Override]
    public function groupBy(string ...$columns): static
    {
        $this->groupByColumns = array_values($columns);

        return $this;
    }

    #[\Override]
    public function addGroupBy(string ...$columns): static
    {
        $this->groupByColumns = [...$this->groupByColumns, ...array_values($columns)];

        return $this;
    }

    #[\Override]
    public function having(string $predicate): static
    {
        $this->havings = [['predicate' => $predicate, 'connector' => '']];

        return $this;
    }

    #[\Override]
    public function andHaving(string $predicate): static
    {
        $this->havings[] = ['predicate' => $predicate, 'connector' => 'AND'];

        return $this;
    }

    // ── ORDER BY / LIMIT ─────────────────────────────────────────────────

    #[\Override]
    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orderBys = [['column' => $column, 'direction' => $direction]];

        return $this;
    }

    #[\Override]
    public function addOrderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orderBys[] = ['column' => $column, 'direction' => $direction];

        return $this;
    }

    #[\Override]
    public function setMaxResults(?int $limit): static
    {
        $this->maxResults = $limit;

        return $this;
    }

    #[\Override]
    public function setFirstResult(int $offset): static
    {
        $this->firstResult = $offset;

        return $this;
    }

    // ── Parameters ───────────────────────────────────────────────────────

    #[\Override]
    public function setParameter(int|string $key, mixed $value, ?int $type = null): static
    {
        $this->parameters[$key] = $value;

        if ($type !== null) {
            $this->parameterTypes[$key] = $type;
        }

        return $this;
    }

    // ── UNION ────────────────────────────────────────────────────────────

    #[\Override]
    public function union(string|QueryBuilder $query): static
    {
        $this->type = 'UNION';
        $sql = $query instanceof QueryBuilder ? $query->sql() : $query;
        $this->unions = [['query' => $sql, 'type' => UnionType::Distinct]];

        return $this;
    }

    #[\Override]
    public function addUnion(string|QueryBuilder $query, UnionType $type = UnionType::Distinct): static
    {
        $sql = $query instanceof QueryBuilder ? $query->sql() : $query;
        $this->unions[] = ['query' => $sql, 'type' => $type];

        return $this;
    }

    // ── CTE (Common Table Expressions) ───────────────────────────────────

    #[\Override]
    public function with(string $name, string|QueryBuilder $query, bool $recursive = false): static
    {
        $sql = $query instanceof QueryBuilder ? $query->sql() : $query;
        $this->ctes[] = ['name' => $name, 'query' => $sql, 'recursive' => $recursive];

        return $this;
    }

    // ── Named/Positional Parameters ──────────────────────────────────────

    #[\Override]
    public function createNamedParameter(mixed $value, ?int $type = null, ?string $placeHolder = null): string
    {
        if ($placeHolder === null) {
            $this->boundCounter++;
            $placeHolder = ':dcValue' . $this->boundCounter;
        }

        $this->setParameter(key: $placeHolder, value: $value, type: $type);

        return $placeHolder;
    }

    #[\Override]
    public function createPositionalParameter(mixed $value, ?int $type = null): string
    {
        // Chave 0-based (pos-incremento), igual ao Doctrine DBAL 4.2: o PDO
        // associa a chave inteira N ao (N+1)-esimo "?" do SQL. Pre-incrementar
        // desloca todos os parametros em uma posicao.
        $this->setParameter(key: $this->boundCounter, value: $value, type: $type);
        $this->boundCounter++;

        return '?';
    }

    #[\Override]
    public function sql(): string
    {
        $sql = match ($this->type) {
            'SELECT' => $this->buildSelect(),
            'INSERT' => $this->buildInsert(),
            'UPDATE' => $this->buildUpdate(),
            'DELETE' => $this->buildDelete(),
            'UNION' => $this->buildUnion(),
            default => throw QueryException::invalidBuilderState(),
        };

        return $this->prependCte($sql);
    }

    #[\Override]
    public function parameters(): array
    {
        return $this->parameters;
    }

    #[\Override]
    public function parameterTypes(): array
    {
        return $this->parameterTypes;
    }

    // ── Execution ────────────────────────────────────────────────────────

    #[\Override]
    public function executeQuery(): Result
    {
        return $this->connection->executeQuery(
            sql: $this->sql(),
            params: $this->parameters,
            types: $this->parameterTypes,
        );
    }

    #[\Override]
    public function executeStatement(): int
    {
        if ($this->type === 'INSERT' && !empty($this->bulkRows)) {
            return $this->executeBulkInsert();
        }

        return $this->connection->executeStatement(
            sql: $this->sql(),
            params: $this->parameters,
            types: $this->parameterTypes,
        );
    }

    // ── SQL Builders ─────────────────────────────────────────────────────

    private function buildSelect(): string
    {
        $platform = $this->connection->platform;
        $columns = implode(', ', $this->selectColumns);
        $distinct = $this->isDistinct ? 'DISTINCT ' : '';
        $table = $this->tableAlias !== null
            ? "{$this->table} {$this->tableAlias}"
            : $this->table;

        $sql = "SELECT {$distinct}{$columns} FROM {$table}";
        $sql .= $this->buildJoins();
        $sql .= $this->buildWhere();
        $sql .= $this->buildGroupBy();
        $sql .= $this->buildHaving();
        $sql .= $this->buildOrderBy();

        return $platform->getLimitSql(query: $sql, limit: $this->maxResults, offset: $this->firstResult ?: null);
    }

    private function buildUnion(): string
    {
        $parts = [];

        foreach ($this->unions as $i => $union) {
            if ($i > 0) {
                $keyword = $union['type'] === UnionType::All ? 'UNION ALL' : 'UNION';
                $parts[] = $keyword;
            }

            $parts[] = $union['query'];
        }

        $sql = implode(' ', $parts);
        $sql .= $this->buildOrderBy();

        $platform = $this->connection->platform;

        return $platform->getLimitSql(query: $sql, limit: $this->maxResults, offset: $this->firstResult ?: null);
    }

    private function prependCte(string $sql): string
    {
        if (empty($this->ctes)) {
            return $sql;
        }

        $hasRecursive = false;
        $cteParts = [];

        foreach ($this->ctes as $cte) {
            if ($cte['recursive']) {
                $hasRecursive = true;
            }

            $cteParts[] = "{$cte['name']} AS ({$cte['query']})";
        }

        $keyword = $hasRecursive ? 'WITH RECURSIVE' : 'WITH';

        return $keyword . ' ' . implode(', ', $cteParts) . ' ' . $sql;
    }

    private function buildInsert(): string
    {
        if (!empty($this->bulkRows)) {
            $columns = array_keys($this->bulkRows[0]);
            $platform = $this->connection->platform;

            return $platform->getBulkInsertSql(table: $this->table, columns: $columns, rowCount: count($this->bulkRows));
        }

        $columns = array_keys($this->insertValues);
        $placeholders = array_fill(0, count($columns), '?');

        return sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders),
        );
    }

    private function buildUpdate(): string
    {
        $sets = [];
        foreach ($this->setClauses as $column => $value) {
            $sets[] = "{$column} = {$value}";
        }

        $table = $this->tableAlias !== null
            ? "{$this->table} {$this->tableAlias}"
            : $this->table;

        $sql = "UPDATE {$table} SET " . implode(', ', $sets);
        $sql .= $this->buildWhere();

        return $sql;
    }

    private function buildDelete(): string
    {
        $table = $this->tableAlias !== null
            ? "{$this->table} {$this->tableAlias}"
            : $this->table;

        $sql = "DELETE FROM {$table}";
        $sql .= $this->buildWhere();

        return $sql;
    }

    // ── Clause Builders ──────────────────────────────────────────────────

    private function buildJoins(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        $parts = [];
        foreach ($this->joins as $join) {
            $parts[] = " {$join['type']} {$join['table']} {$join['alias']} ON {$join['condition']}";
        }

        return implode('', $parts);
    }

    private function buildWhere(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $parts = [];
        foreach ($this->wheres as $i => $where) {
            $prefix = $i === 0 ? '' : " {$where['connector']} ";
            $parts[] = $prefix . $where['predicate'];
        }

        return ' WHERE ' . implode('', $parts);
    }

    private function buildGroupBy(): string
    {
        if (empty($this->groupByColumns)) {
            return '';
        }

        return ' GROUP BY ' . implode(', ', $this->groupByColumns);
    }

    private function buildHaving(): string
    {
        if (empty($this->havings)) {
            return '';
        }

        $parts = [];
        foreach ($this->havings as $i => $having) {
            $prefix = $i === 0 ? '' : " {$having['connector']} ";
            $parts[] = $prefix . $having['predicate'];
        }

        return ' HAVING ' . implode('', $parts);
    }

    private function buildOrderBy(): string
    {
        if (empty($this->orderBys)) {
            return '';
        }

        $parts = array_map(
            fn(array $o) => "{$o['column']} {$o['direction']}",
            $this->orderBys,
        );

        return ' ORDER BY ' . implode(', ', $parts);
    }

    // ── Bulk Insert ──────────────────────────────────────────────────────

    private function executeBulkInsert(): int
    {
        $platform = $this->connection->platform;
        $columns = array_keys($this->bulkRows[0]);
        $colCount = count($columns);
        $maxPlaceholders = $platform->maxPlaceholders();
        $maxRowsPerChunk = max((int) floor($maxPlaceholders / max($colCount, 1)), 1);
        $chunks = array_chunk($this->bulkRows, $maxRowsPerChunk);

        $totalAffected = 0;

        foreach ($chunks as $chunk) {
            $sql = $platform->getBulkInsertSql(table: $this->table, columns: $columns, rowCount: count($chunk));
            $params = array_merge(...array_map(
                static fn(array $row): array => array_map(static fn(string $col): mixed => $row[$col] ?? null, $columns),
                $chunk,
            ));

            $totalAffected += $this->connection->executeStatement($sql, $params);
        }

        return $totalAffected;
    }
}
