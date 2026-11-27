# ADR-012 — Bulk Insert with Platform-Aware Auto-Chunking

| Field  | Value      |
|--------|------------|
| Status | Accepted   |
| Date   | 2026-03-08 |

## Context

DataFlow ETL pipelines insert thousands of rows per batch. Single-row INSERT is 10–100x slower than multi-row. Laravel has `DB::table()->insert([rows])`. Doctrine DBAL has no bulk insert at the DBAL level.

## Decision

`QueryBuilder::bulkValues(array $rows): self` generates `INSERT INTO t (c1, c2) VALUES (?, ?), (?, ?), ...`. The Platform provides `getMaxPlaceholders(): int` (MySQL: 65535, SQLite: 999, PostgreSQL: 34464). If `rowCount × columnCount` exceeds the limit, auto-chunks into multiple INSERT statements within the current transaction.

## Alternatives Considered

1. **COPY / LOAD DATA** — Platform-specific bulk load. Rejected: requires file I/O or special permissions, not portable.
2. **Consumer manages chunking** — Let DataFlow split rows. Rejected: every consumer reimplements the same placeholder math.

## Consequences

**Positive:** 10–100x faster than single-row INSERT. Portable across all 3 platforms. Transparent chunking.
**Negative:** Multi-row INSERT not supported on very old MySQL (<5.7) — but we require 5.7+ anyway.

## References

- MySQL max_allowed_packet and placeholder limits
- SQLite SQLITE_MAX_VARIABLE_NUMBER (default 999, compile-time configurable)
- PostgreSQL max parameters: 34464 (INT16_MAX / 2)
