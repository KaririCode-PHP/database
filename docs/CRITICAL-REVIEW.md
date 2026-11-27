# Critical Review — KaririCode\Database Design v1

**Date:** 2026-03-08
**Reviewer:** Deep pass #2 — cross-referencing against 50+ ecosystem chats, actual ORM/DataFlow/Tenancy code, and ARFA 1.3 spec

---

## P0 — Design Flaws (Must Fix Before Implementation)

### ISSUE-001: Contract Location Bootstrap Paradox

**§3 states:** "All contracts live in `kariricode/contract` under `KaririCode\Contract\Database`"
**§12 File Inventory lists:** `src/Contract/` with 9 interfaces inside `kariricode/database`

These are contradictory. If contracts live in `kariricode/contract`, then `kariricode/database` has zero `src/Contract/` directory. If they start in `kariricode/database`, then the §3 statement is premature.

**Reality:** `kariricode/contract` V3.1 has 55+ interfaces and hasn't been upgraded to V4.0 yet. The upgrade tracker (chat 8b26e798) specifically flags Contract V4.0 as the next critical milestone. Database contracts MUST start local in `src/Contract/` and be promoted to `kariricode/contract` V4.1 in Phase 2.

**Fix:** Rewrite §3 and §12 to clarify two-phase interface promotion. Phase 1: interfaces in `kariricode/database/src/Contract/`. Phase 2 (Contract V4.1): promote to `kariricode/contract`. §15 Migration Plan mentions this but §3 contradicts it.

---

### ISSUE-002: Generator-Backed Cursors Missing from Result Contract

**§1.2 promises:** "Result set abstraction with generator-backed cursors"
**§3.1 Result interface:** Only `fetchAllAssociative(): array`, `fetchNumeric(): array|false`, etc.

There is NO `fetchLazy(): \Generator` or `cursor(): iterable` method. DataFlow V4.1 was specifically built around generator-backed iteration for memory-efficient ETL pipelines (`DataCursor` using `yield`). If the Result contract doesn't support lazy iteration, DataFlow cannot migrate to `kariricode/database` without a shim.

**Fix:** Add to the Result contract:
- `iterateAssociative(): \Generator<int, array<string, mixed>>` — row-by-row generator
- `iterateNumeric(): \Generator<int, array<int, mixed>>` — numeric index generator
- `iterateColumn(int $index = 0): \Generator<int, mixed>` — single column generator

These are the primitives DataFlow needs. The PdoResult implementation wraps `PDOStatement::fetch()` in a `while` loop with `yield`.

---

### ISSUE-003: Query Builder Misplaced in Non-Goals

**§1.3 states:** "Query builder (fluent) → `kariricode/orm` — Tightly coupled to entity model"

This is factually wrong. A basic SQL query builder (SELECT/INSERT/UPDATE/DELETE with WHERE/JOIN/ORDER BY/LIMIT) is entity-agnostic. Evidence:

- DataFlow V3.1 had `FluentQueryBuilder` in its own package — no entity awareness
- Doctrine DBAL 4 has `QueryBuilder` in the DBAL package, not the ORM
- Laravel has `Illuminate\Database\Query\Builder` separate from Eloquent

If Database excludes query builder, BOTH ORM AND DataFlow will reimplement it — exactly the duplication Database exists to prevent.

**Fix:** Split query builder into two layers:
1. **Database (Phase 1):** SQL query builder — string manipulation, parameter binding, dialect-aware. Lives in `kariricode/database`.
2. **ORM:** Entity-aware query builder — extends Database's builder with entity mapping, relation loading, DPO hydration.

Add a Phase 1.5 to §15 Migration Plan for the SQL query builder.

---

### ISSUE-004: NoSQL Silently Dropped

**§1 states:** "with optional NoSQL support through driver extensions"
**Entire design:** SQL-only. No MongoDB, no Redis driver. No document/key-value contract.

DataFlow V3.1 shipped with `MongoDBConnection` and `RedisConnection`. DataFlow V4.1 still has these. Dropping NoSQL without justification means DataFlow V4.2 cannot migrate those connections to `kariricode/database`.

**Fix:** Two options:
- **Option A (recommended):** Explicitly declare SQL-only scope in §1.1 and remove the "optional NoSQL" claim. Add a future ADR for NoSQL support. DataFlow keeps its own MongoDB/Redis connections until then.
- **Option B:** Add thin NoSQL driver contracts now (DocumentDriver, KeyValueDriver) as Phase 3 contracts, documented but unimplemented.

---

## P1 — Architectural Gaps (Should Fix)

### ISSUE-005: ReadWriteConnection + Transaction Semantics Undefined

**ADR-006 states:** "Transactions always use the write primary"
**Not specified:** What happens when:
1. A read query is already running on a replica when `beginTransaction()` is called
2. A `SELECT ... FOR UPDATE` (which is a read query syntax but requires write lock) is issued
3. `transactional()` callback contains only `executeQuery()` calls

Doctrine's `PrimaryReadReplicaConnection` has a well-documented solution: calling `beginTransaction()` forces ALL subsequent operations (including reads) to the primary until `commit()`/`rollBack()`. This is not documented in the design.

**Fix:** Add to ADR-006 §Decision: "After `beginTransaction()`, the ReadWriteConnection enters primary-only mode. All operations route to write primary until the transaction completes. This ensures transactional isolation regardless of read/write classification."

---

### ISSUE-006: Middleware Order × TenancyMiddleware × RetryMiddleware Conflict

If the middleware stack is: `Logging → Retry → Tenancy → Driver`, then when RetryMiddleware reconnects after a transient failure, the new connection passes through Tenancy middleware (correct — schema gets set). But if the stack is: `Logging → Tenancy → Retry → Driver`, then Retry reconnects at the Driver level BELOW Tenancy — the new connection never gets `SET search_path`.

**Fix:** ADR-002 must document a required ordering constraint:
- RetryMiddleware MUST wrap outermost (or at minimum, wrap around TenancyMiddleware)
- TenancyMiddleware MUST be closest to the Driver
- Recommended order: `Logging → Profiling → Retry → Tenancy → Driver`

---

### ISSUE-007: Two Type Systems (Database vs ORM)

The ORM V4.5 already has an attribute-based type system (`#[Column(type: 'datetime')]`, `AttributeType` mappings). If Database also ships a `TypeRegistry`, there are two type systems in the stack.

**Fix:** Clarify the boundary in SPEC §8:
- Database `TypeRegistry`: converts raw DB values to PHP primitives (string→DateTimeImmutable, int→bool, etc.)
- ORM type system: maps entity properties to columns and delegates to Database's `TypeConverter` for the actual conversion
- Database types are the foundation; ORM types compose them with entity metadata

---

### ISSUE-008: SchemaInspector Needs Schema Qualification

In multi-tenant PostgreSQL with schema isolation (Tenancy V4.0 pattern), `listTableNames()` returns tables from the current `search_path`. But an admin tool or migration runner may need to inspect a specific schema without changing the connection's `search_path`.

**Fix:** Add optional `?string $schema = null` parameter to all SchemaInspector methods:
- `listTableNames(?string $schema = null): array`
- `listTableColumns(string $table, ?string $schema = null): array`
- etc.

When `$schema` is null, use the connection's current schema. When provided, qualify queries with the schema name explicitly.

---

### ISSUE-009: Advisory Lock Convenience Missing

The ORM uses advisory locks extensively (XOR-folded UUIDs per ADR-026 GAP-8). The Platform exposes `getAdvisoryLockSql()` and `supportsAdvisoryLocks()` but the Connection has no convenience method.

Consumer code currently:
```php
$conn->executeStatement($conn->getPlatform()->getAdvisoryLockSql($key));
```

This is verbose and error-prone (forgetting to check `supportsAdvisoryLocks()` first).

**Fix:** Add to Connection contract:
- `advisoryLock(int|string $key): void` — acquires transaction-scoped advisory lock
- `advisoryUnlock(int|string $key): void` — releases (no-op for transaction-scoped)

These delegate to Platform methods and throw `PlatformException::unsupportedFeature()` on SQLite.

---

## P2 — Scope/Efficiency Issues (Consider)

### ISSUE-010: ext-mysqli and ext-pgsql Drivers in Phase 1 Are Speculative

No existing ecosystem package uses `ext-mysqli` or `ext-pgsql` directly — all use PDO. These two drivers add ~400 LOC + tests + exception converters for zero current consumers.

**Fix:** Move `MysqliDriver` and `PgSqlDriver` to Phase 3 in §15. Phase 1 ships PDO-only (3 drivers), which covers 100% of current ecosystem needs.

---

### ISSUE-011: Error Code Range — Verify No Collision

Proposed range: DB-6000–6199

Known V4.0 ecosystem ranges:
| Range       | Package          |
|-------------|------------------|
| 1000–1499   | AsyncHelper      |
| 2000–2499   | EventLoop        |
| 3000–3049   | Async            |
| 3100–3169   | DI               |
| 4000–4099   | Queue            |
| 5000–5199   | DataFlow         |
| 7000–7099   | Template         |
| 7100–7199   | CircuitBreaker   |
| 8100–8199   | EventDispatcher  |
| 8200–8249   | Observability    |
| 8300–8399   | RateLimiter      |
| 9000–9099   | Console          |
| 9100–9199   | I18n             |
| 9200–9299   | Tenancy          |

**Range 6000–6199 is CLEAR.** No collision detected. But the Email V3.1 (not yet V4.0) used 6000-6999 internally — when Email is upgraded to V4.0, its range must avoid 6000–6199.

**Fix:** Document in SPEC §10 the neighboring reserved range: "DB-6000–6199 allocated. Email V4.0 MUST use 6200+ when upgraded." Also verify ORM's range (likely 5200–5399 or 6200+).

---

### ISSUE-012: ConnectionParams Password Exposure

`ConnectionParams` is a `final readonly class` with `password` as a public readonly property. This means:
- Password lives in memory for the connection's entire lifetime
- Any code with a reference to `ConnectionParams` can read the password
- Stack traces / debug dumps may expose it

**Fix:** Two options:
- **Minimal:** Mark `password` as `private(set)` with a `getPassword(): string` method, and add `__debugInfo()` that redacts the password. This at least prevents casual exposure.
- **Full:** Accept a `Closure(): string` for password instead of a string. The closure is called once during connect and the result is not stored. This is the most secure but less ergonomic.

Recommend the minimal approach for Phase 1.

---

### ISSUE-013: Performance Targets Are Unmeasurable

"Connection reuse < 0.1ms" is just an array key lookup — trivially true and not useful. "Middleware overhead < 0.05ms" has no measurement methodology.

**Fix:** Either:
- Remove the Performance Targets section (aspirational targets without methodology are noise)
- Add measurement methodology: "Measured via PhpBench with warm JIT, SQLite in-memory, 10K iterations, P99 latency reported"

---

### ISSUE-014: ExceptionConverter Duplication

Three separate `ExceptionConverter` implementations (MySQL, PostgreSQL, SQLite) will share ~60% of their code (SQLSTATE-based conversion is standardized). Only driver-specific error codes differ.

**Fix:** Add a shared `SqlStateExceptionConverter` that handles standard SQLSTATE codes, with per-driver subconverters for vendor-specific codes. File inventory: 1 shared + 3 vendor = 4 files (vs 3 files with duplication).

---

## Summary

| Severity | Count | Issues |
|----------|:-----:|--------|
| P0 — Must fix | 4 | Contract bootstrap paradox, missing generators, query builder placement, NoSQL dropped |
| P1 — Should fix | 5 | R/W transaction semantics, middleware ordering, dual type systems, schema qualification, advisory locks |
| P2 — Consider | 5 | Phase 1 scope, error code neighbors, password exposure, perf targets, exception converter |
| **Total** | **14** | |

All P0 items change the SPEC. P1 items add specificity to existing sections. P2 items are optimizations.
