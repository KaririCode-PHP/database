# ADR-005 — Savepoint-Based Nested Transactions

| Field  | Value      |
|--------|------------|
| Status | Accepted   |
| Date   | 2026-03-08 |

## Context

Application code frequently needs nested transactional boundaries: a service method wraps its work in a transaction, but is called from a controller that also wraps in a transaction. Without savepoint support, the inner `beginTransaction()` either fails or silently becomes a no-op.

## Decision

`DefaultConnection` tracks a `transactionNestingLevel: int`. The first `beginTransaction()` issues a real `BEGIN`. Subsequent calls create a `SAVEPOINT kariricode_sp_{level}`. `commit()` at depth > 1 releases the savepoint; at depth 1 issues real `COMMIT`. `rollBack()` at depth > 1 rolls back to the savepoint; at depth 1 issues real `ROLLBACK`.

If the platform does not support savepoints (`$platform->supportsSavepoints() === false`), nested `beginTransaction()` throws `PlatformException::savepointsNotSupported()` rather than silently ignoring.

## Alternatives Considered

1. **Silent nesting (Laravel model)** — Increment counter, only real BEGIN/COMMIT at outermost level. Rejected: inner rollback silently ignored, data corruption risk.
2. **Flat transactions only** — Throw on nested `beginTransaction()`. Rejected: breaks composability of service methods.
3. **Two-phase commit** — Distributed transaction protocol. Rejected: out of scope for single-database DBAL.

## Consequences

**Positive:**
- Inner rollback truly rolls back inner work without affecting outer transaction
- Composable transactional service methods
- Explicit failure on unsupported platforms (no silent data loss)

**Negative:**
- Savepoint naming must be unique per connection (solved: counter-based naming)
- Performance overhead: ~0.1ms per savepoint operation (negligible)
