# ADR-002 — Middleware Decoration Pipeline

| Field  | Value      |
|--------|------------|
| Status | Accepted   |
| Date   | 2026-03-08 |

## Context

Cross-cutting concerns (logging, profiling, tenancy context, retry logic) must attach to database operations without modifying driver code. The ecosystem already has `kariricode/logging` and `kariricode/observability` but database packages cannot hard-depend on them.

## Decision

Adopt Doctrine DBAL 4's middleware pattern: each `Middleware` implements `wrap(Driver): Driver`, returning a decorated Driver that can intercept `connect()`, and the resulting `DriverConnection` can intercept `prepare()`, `exec()`, `beginTransaction()`, etc.

Middlewares are registered on `ConnectionFactory` and applied in LIFO order (first registered = outermost wrapper).

Four built-in middlewares ship with the package:
- `LoggingMiddleware` — accepts a `callable(string $sql, array $params, float $timeMs): void`
- `ProfilingMiddleware` — collects `QueryResult` VOs, exposes `getProfiles(): array`
- `TenancyMiddleware` — executes SQL on connect (e.g., `SET search_path TO ?`)
- `RetryMiddleware` — retries on `deadlock()` and `connectionLost()` (configurable max retries)

All use duck-typed callables or lightweight interfaces — zero hard dependency on ecosystem packages. Bridges (`LoggingBridge`, `ObservabilityBridge`) adapt the callables to real ecosystem packages when present.

## Alternatives Considered

1. **Event-based (PSR-14)** — Fire events before/after queries. Rejected: PSR-14 adds a hard dependency, and events are fire-and-forget — cannot modify the query or retry on failure.
2. **Aspect-Oriented (proxy generation)** — Generate proxies at compile time. Rejected: complex build step, incompatible with `final` classes per ARFA 1.3.
3. **Trait-based** — Mix in logging/profiling via traits. Rejected: violates composition-over-inheritance principle, not configurable at runtime.

## Consequences

**Positive:**
- Zero coupling to logging/observability packages
- Runtime-configurable middleware stack
- Same pattern as Doctrine DBAL 4 — familiar to PHP developers
- Each middleware is independently testable

**Negative:**
- Deep decoration chains can obscure stack traces (mitigated by exception conversion at each layer)
- Middleware ordering is critical — required order (outermost → innermost): Logging → Profiling → Retry → Tenancy → Driver. RetryMiddleware MUST wrap TenancyMiddleware so reconnections re-execute `SET search_path`. Ref: PostgreSQL §13.3.5 — advisory locks and schema paths are connection-scoped state that must be re-established on reconnect
