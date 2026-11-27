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
