# ADR-009 — SQL Query Builder in DBAL Layer

| Field  | Value      |
|--------|------------|
| Status | Accepted   |
| Date   | 2026-03-08 |

## Context

Both KaririCode\ORM and KaririCode\DataFlow need to construct SQL queries programmatically. Without a shared query builder in the DBAL, both packages reimplement fluent SQL generation independently — exactly the duplication that `kariricode/database` exists to prevent.

The initial design (v1) placed query builder as a non-goal, claiming it was "tightly coupled to entity model." Critical review (ISSUE-003) identified this as factually incorrect: a basic SQL query builder (`SELECT`/`INSERT`/`UPDATE`/`DELETE` with `WHERE`/`JOIN`/`ORDER BY`/`LIMIT`) is entity-agnostic — it manipulates SQL strings and parameter bindings, nothing more.

## Decision

`kariricode/database` includes `SqlQueryBuilder` — an entity-agnostic, dialect-aware SQL query builder. It provides fluent methods for constructing SQL statements, delegates dialect differences (LIMIT/OFFSET syntax, identifier quoting) to the Platform, and binds parameters safely.

The ORM extends this builder with entity-aware features (DPO hydration, relation loading, attribute-based column mapping). DataFlow uses it directly for ETL query construction.

Access via factory: `$conn->createQueryBuilder(): QueryBuilder`.

**Scope of the DBAL query builder:**
- SELECT, INSERT, UPDATE, DELETE statements
- WHERE, AND WHERE, OR WHERE with positional/named parameters
- JOINs (INNER, LEFT, RIGHT)
- GROUP BY, HAVING
- ORDER BY, LIMIT/OFFSET (dialect-aware via Platform)
- Expression builder for composing AND/OR predicates
- Parameter binding with type safety

**NOT in scope (ORM responsibility):**
- Entity hydration, DPO output
- Relation eager/lazy loading
- Identity map, change tracking, Unit of Work

## Alternatives Considered

1. **Query builder only in ORM** — DataFlow V3.1 already had `FluentQueryBuilder` without entities. Placing it only in ORM forces DataFlow to either depend on ORM (circular risk) or reimplement. Rejected.
2. **String concatenation in consumers** — Let consumers build SQL manually. Rejected: SQL injection risk, no dialect awareness, duplicated LIMIT/OFFSET handling.
3. **Full expression language** — A complete SQL AST parser/generator. Rejected: scope creep; Doctrine abandoned this approach in DBAL 4 in favor of simple string-based builder.

## Consequences

**Positive:**
- Single query builder implementation for the ecosystem
- ORM extends (not replaces) — clean layering
- DataFlow uses directly without ORM dependency
- Dialect differences centralized in Platform

**Negative:**
- Larger DBAL package surface (+3 files, ~500 LOC)
- Must resist scope creep — entity-aware features MUST stay in ORM

## References

- Doctrine DBAL 4.4: `Doctrine\DBAL\Query\QueryBuilder` lives in DBAL, not ORM
- Doctrine DBAL docs — "SQL Query Builder": "QueryBuilder class is responsible to dynamically create SQL queries."
- DataFlow V3.1 — `FluentQueryBuilder` (entity-agnostic, no ORM dependency)
- Fowler (2002) — *PoEAA* §11 — "Query Object" pattern: "An object that represents a database query"
