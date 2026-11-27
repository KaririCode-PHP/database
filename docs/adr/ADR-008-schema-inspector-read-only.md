# ADR-008 — Schema Inspector: Read-Only by Design

| Field  | Value      |
|--------|------------|
| Status | Accepted   |
| Date   | 2026-03-08 |

## Context

Schema inspection (listing tables, columns, indexes, foreign keys) is needed by the ORM (entity mapping validation), Tenancy (schema existence checks), and tooling (CLI introspection). Schema *modification* (CREATE TABLE, ALTER, DROP) is tightly coupled to entity metadata and migration versioning — concerns that belong in the ORM.

Doctrine DBAL bundles both inspection and modification in its `SchemaManager`, creating a 1,500-line class with 40+ methods that conflates read and write responsibilities.

## Decision

KaririCode\Database provides `SchemaInspector` as a **read-only** contract with 5 methods (list tables, columns, indexes, foreign keys, table existence check). Each platform has its own implementation querying `INFORMATION_SCHEMA` or `sqlite_master`.

Schema modification (DDL generation, migration) is explicitly out of scope — it lives in `kariricode/orm`.

Return values are readonly value objects (`ColumnMetadata`, `IndexMetadata`, `ForeignKeyMetadata`, `TableMetadata`) — not mutable schema representation objects.

## Alternatives Considered

1. **Full SchemaManager (Doctrine model)** — Include `createTable()`, `alterTable()`, `dropTable()`. Rejected: creates tight coupling between DBAL and entity model; migration versioning belongs in ORM.
2. **No schema inspection** — Let consumers query `INFORMATION_SCHEMA` directly. Rejected: vendor-specific SQL for each platform, duplicated across ORM/Tenancy.
3. **Mutable schema objects** — Return `Table`, `Column` objects that can be modified and diffed. Rejected: ARFA 1.3 P1 (immutability); mutable schema objects are an ORM concern.

## Consequences

**Positive:**
- SchemaInspector is small (~150 LOC per platform), easy to maintain
- Readonly VOs prevent accidental schema modifications
- Clear separation: DBAL reads schema, ORM writes schema

**Negative:**
- ORM must implement its own DDL generation (but it already does for migration support)
