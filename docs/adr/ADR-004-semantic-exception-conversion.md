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

## Amendment — 2026-11-27: the converters are wired in

Until 4.4.0 the converters existed and were tested, but no driver called them: every statement error reached the caller as a raw `PDOException`, and every refused connection was `ConnectionException` 6010, wrong credentials included. Running the suite against PostgreSQL 16 and MariaDB 10.11 showed it.

- `PdoConnection`, `PdoStatement` and `PdoResult` take the driver's converter and convert every `PDOException` of prepare, exec, execute, fetch, the transaction calls and `lastInsertId()`. `fetchAll()` of pdo_sqlite returns the rows read before an error without throwing; `PdoResult` reads `errorInfo()` after each fetch and converts that error too.
- The drivers convert a refused connection with `AbstractExceptionConverter::convertConnectFailure()`: only configuration and connection outcomes remain (a lost connection is a failed one, anything else is 6010), so `RetryMiddleware` retries a full or unreachable server but not wrong credentials.
- libpq reports every refused connection as SQLSTATE 08006, so `PostgreSqlExceptionConverter` reads the server message at connect time: "password authentication failed" is 6012, `database "x" does not exist` 6013, "too many clients" 6015. With translated server messages the result is 6010, never a wrong family.
- New vendor codes: MySQL 1040 (too many connections), 1044 (no access to the database), 2013 (lost during a query); SQLite result codes 5 and 6 (busy, locked) are lock timeouts (6036).
