# ADR-013 — Connection Lifecycle Events

| Field  | Value      |
|--------|------------|
| Status | Accepted   |
| Date   | 2026-03-08 |

## Context

Doctrine emits `postConnect` events via EventManager. Laravel emits `QueryExecuted`, `DatabaseBusy` events. Our middleware captures query execution but doesn't emit formal events for connection lifecycle.

## Decision

6 event VOs: `ConnectionEstablished`, `QueryExecuted`, `TransactionBegun`, `TransactionCommitted`, `TransactionRolledBack`, `ConnectionError`. Published via `EventDispatcherBridge` — a duck-typed `callable(object): void`. Zero hard dependency on `kariricode/event-dispatcher`.

All events implement `DataStoreEvent` marker interface (shared with `kariricode/nosql`) for unified observability.

## Alternatives Considered

1. **PSR-14 hard dependency** — Use EventDispatcherInterface. Rejected: adds external dependency. Our bridge pattern is duck-typed.
2. **No events** — Middleware handles everything. Rejected: middleware cannot observe connection lifecycle (connect/disconnect).

## Consequences

**Positive:** Unified observability with NoSQL. `kariricode/observability` receives events from both SQL and NoSQL.
**Negative:** +6 small VO files.

## Amendment — 2026-11-27: PSR-14, and ConnectionEstablished is emitted

4.3.0 replaced the duck-typed bridge with a PSR-14 `EventDispatcherInterface` (ADR-015), and the six events stayed. `ConnectionEstablished` was never dispatched, although SPEC-001 §2.3 names `ConnectionFactory` as its source. From 4.4.0 `ConnectionFactory::createConnection(ConnectionParams $params, string $name = 'default')` dispatches it after the connect, with the name `ConnectionRegistry` passes, and dispatches `ConnectionError` when the connect fails, before rethrowing.

Listeners of `QueryExecuted` and `ConnectionError` receive the bound values; they must redact them before logging.
