# ADR-003 — Platform Dialect Abstraction

| Field  | Value      |
|--------|------------|
| Status | Accepted   |
| Date   | 2026-03-08 |

## Context

SQL dialects differ across databases: identifier quoting (backtick vs double-quote), boolean literals (1/0 vs TRUE/FALSE), LIMIT/OFFSET syntax, savepoint support, JSON operators, and UPSERT syntax. Hardcoding dialect-specific SQL in consumer packages defeats the purpose of a DBAL.

## Decision

Each supported database has a `Platform` implementation (`MySqlPlatform`, `PostgreSqlPlatform`, `SqlitePlatform`) that encapsulates dialect differences behind a uniform interface. The Platform is created by the Driver during connection and exposed via `Connection::getPlatform()`.

Platforms are `final readonly` classes — no inheritance hierarchy. Shared behavior is extracted to private helper methods within each platform, not to an abstract base class. This avoids the Doctrine trap of a 2,000-line `AbstractPlatform` that every platform inherits.

Feature detection uses capability methods (`supportsSavepoints()`, `supportsSchemas()`, `supportsReturning()`) rather than runtime version sniffing.

## Alternatives Considered

1. **Abstract base class (Doctrine model)** — `AbstractPlatform` with 50+ overridable methods. Rejected: violates ARFA 1.3 max-2-level inheritance and creates a fragile base class.
2. **SQL builder DSL** — A fluent builder that emits dialect-specific SQL. Rejected: scope creep into query builder territory (ORM's responsibility).
3. **No abstraction** — Let consumers write dialect-specific SQL. Rejected: defeats the purpose of DBAL, creates coupling throughout the ecosystem.

## Consequences

**Positive:**
- Each platform is self-contained (~200 LOC), easy to audit
- New platforms (MariaDB-specific, CockroachDB) can be added without touching existing code
- Capability methods prevent runtime errors from unsupported features

**Negative:**
- Some code duplication across platforms (mitigated: shared SQL standard behavior is minimal)
- Adding a new feature requires touching all 3 platform files
