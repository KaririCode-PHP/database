# ADR-004 — Semantic Exception Conversion

| Field  | Value      |
|--------|------------|
| Status | Accepted   |
| Date   | 2026-03-08 |

## Context

Database drivers throw vendor-specific exceptions: `PDOException` with SQLSTATE codes, `mysqli_sql_exception` with MySQL error codes, `pg_last_error()` strings. Consumer code cannot reliably catch specific error conditions (e.g., "unique constraint violation") without vendor-specific `catch` blocks.

## Decision

Each driver ships with an `ExceptionConverter` that maps vendor-specific error codes to semantic KaririCode exceptions via static factory methods. Conversion happens at the Driver layer boundary — the Connection never sees raw PDOException.

Mapping tables are maintained per driver:

- MySQL: error codes (1045, 1062, 1048, 1451, 1213, etc.)
- PostgreSQL: SQLSTATE codes (23505, 23503, 23502, 40001, 40P01, etc.)
- SQLite: SQLSTATE + error messages (regex matching for constraint names)

Each semantic exception carries the original vendor exception as `$previous`, preserving full diagnostic context.

## Alternatives Considered

1. **SQLSTATE-only mapping** — Use only the 5-character SQLSTATE code. Rejected: SQLSTATE is too coarse (e.g., `23000` covers unique, FK, and NOT NULL violations in MySQL — only the error code disambiguates).
2. **Passthrough with code constants** — Let PDOException propagate, provide constants for checking. Rejected: forces `instanceof PDOException` in consumer code, couples to PDO.
3. **Error code enum on exception** — Single `DatabaseException` with an error code enum. Rejected: prevents typed `catch` blocks (`catch (UniqueViolation $e)`), less ergonomic.

## Consequences

**Positive:**
- Consumer code uses `catch (QueryException\uniqueViolation)` — driver-agnostic
- Full vendor context preserved in `$previous`
- Error code range DB-6000–6199 avoids collisions with all other ecosystem packages

**Negative:**
- Mapping tables require maintenance when new DB versions add error codes
- Edge cases where vendor error codes overlap or are ambiguous
