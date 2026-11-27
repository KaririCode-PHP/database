# ADR-001 — Two-Layer Driver/Connection Architecture

| Field  | Value      |
|--------|------------|
| Status | Accepted   |
| Date   | 2026-03-08 |

## Context

Database abstraction requires separating the thin, extension-specific code (PDO calls, mysqli calls) from the rich application-level features (transaction nesting, type conversion, middleware). Mixing both in a single class creates a God Object that is impossible to decorate or test in isolation.

Doctrine DBAL 4 solves this with two distinct layers — Driver (thin: Connection, Statement, Result) and Wrapper (rich: Connection wrapping Driver\Connection). Laravel takes a different approach with a single `Connection` class per driver that mixes both concerns, leading to large classes (~1,200 LOC in `MySqlConnection`).

## Decision

KaririCode\Database uses a **two-layer architecture**:

**Driver Layer** (thin, extension-specific):
- `Driver` → creates `DriverConnection`
- `DriverConnection` → creates `Statement`
- `Statement` → produces `Result`
- Each interface has 3–6 methods maximum

**Connection Layer** (rich, driver-agnostic):
- `DefaultConnection` wraps a `DriverConnection`
- Adds transaction nesting, type conversion, savepoints
- Is the public API that consumers (ORM, DataFlow) use

The Middleware pipeline sits between the two layers, decorating Driver components before they reach the Connection wrapper.

## Alternatives Considered

1. **Single-layer (Laravel model)** — One `Connection` class per driver. Rejected: impossible to decorate without subclassing, high code duplication across drivers.
2. **Three-layer with QueryBuilder** — Add a query-building layer inside Database. Rejected: query building is entity-model-specific (ORM responsibility), adding it here would create scope creep.
3. **PDO-only** — Skip the Driver abstraction, use PDO directly. Rejected: prevents ext-mysqli and ext-pgsql native drivers, limits performance optimization opportunities.

## Consequences

**Positive:**
- Middleware can decorate any layer independently
- Drivers are small (~100 LOC each), easy to test
- Connection is driver-agnostic, testable with mock drivers
- New drivers (ext-mysqli, ext-pgsql) can be added without touching Connection

**Negative:**
- More interfaces than a single-class approach
- Indirection cost: one extra method call per query (negligible: < 0.01ms)
