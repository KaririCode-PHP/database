<?php

declare(strict_types=1);

namespace KaririCode\Database\Contract;

use KaririCode\Database\Enum\UnionType;

// ── QueryBuilder ─────────────────────────────────────────────────────
//
// Entity-agnostic fluent SQL builder. Matches Doctrine DBAL 4.4
// feature set: SELECT/INSERT/UPDATE/DELETE, UNION, CTE (WITH),
// DISTINCT, all JOIN types, named/positional parameters.
// ─────────────────────────────────────────────────────────────────────

interface QueryBuilder
{
    // ── SELECT ───────────────────────────────────────────────────────────

    public function select(string ...$columns): static;

    public function distinct(bool $flag = true): static;

    public function from(string $table, ?string $alias = null): static;

    // ── INSERT ───────────────────────────────────────────────────────────

    public function insert(string $table): static;

    /**
     * @param array<string, mixed> $values
     */
    public function values(array $values): static;

    /** @param array<int, array<string, mixed>> $rows */
    public function bulkValues(array $rows): static;

    // ── UPDATE ───────────────────────────────────────────────────────────

    public function update(string $table, ?string $alias = null): static;

    public function set(string $column, string $value): static;

    // ── DELETE ───────────────────────────────────────────────────────────

    public function delete(string $table, ?string $alias = null): static;

    // ── WHERE ────────────────────────────────────────────────────────────

    public function where(string $predicate): static;

    public function andWhere(string $predicate): static;

    public function orWhere(string $predicate): static;

    // ── JOIN ─────────────────────────────────────────────────────────────

    public function innerJoin(string $fromAlias, string $table, string $alias, string $condition): static;

    public function leftJoin(string $fromAlias, string $table, string $alias, string $condition): static;

    public function rightJoin(string $fromAlias, string $table, string $alias, string $condition): static;

    // ── GROUP BY / HAVING ────────────────────────────────────────────────

    public function groupBy(string ...$columns): static;

    public function addGroupBy(string ...$columns): static;

    public function having(string $predicate): static;

    public function andHaving(string $predicate): static;

    // ── ORDER BY / LIMIT ─────────────────────────────────────────────────

    public function orderBy(string $column, string $direction = 'ASC'): static;

    public function addOrderBy(string $column, string $direction = 'ASC'): static;

    public function setMaxResults(?int $limit): static;

    public function setFirstResult(int $offset): static;

    // ── UNION ────────────────────────────────────────────────────────────

    public function union(string|self $query): static;

    public function addUnion(string|self $query, UnionType $type = UnionType::Distinct): static;

    // ── CTE (Common Table Expressions) ───────────────────────────────────

    public function with(string $name, string|self $query, bool $recursive = false): static;

    // ── Parameters ───────────────────────────────────────────────────────

    public function setParameter(int|string $key, mixed $value, ?int $type = null): static;

    public function createNamedParameter(mixed $value, ?int $type = null, ?string $placeHolder = null): string;

    public function createPositionalParameter(mixed $value, ?int $type = null): string;

    public function sql(): string;

    /** @return array<int|string, mixed> */
    public function parameters(): array;

    /** @return array<int|string, int> */
    public function parameterTypes(): array;

    // ── Execution ────────────────────────────────────────────────────────

    public function executeQuery(): Result;

    public function executeStatement(): int;
}
