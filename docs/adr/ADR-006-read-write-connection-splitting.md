# ADR-006 — Read/Write Connection Splitting

| Field  | Value      |
|--------|------------|
| Status | Accepted   |
| Date   | 2026-03-08 |

## Context

Production deployments commonly use read replicas to scale SELECT workloads. The DBAL should support transparent query routing without requiring consumer code to manage multiple connections explicitly.

## Decision

`ReadWriteConnection` is a decorator that holds a write `Connection` and one or more read `Connection` instances. Routing rules follow the Doctrine DBAL 4 `PrimaryReadReplicaConnection` semantics (confirmed by Symfony docs §"How to Use Doctrine DBAL" and Symfony AI issue #1283, Dec 2025):

- `executeQuery()` → random read replica (if no prior write and no active transaction)
- `executeStatement()` → write primary (always; forces primary-only mode)
- `beginTransaction()` → **forces primary-only mode** for ALL subsequent operations (including SELECTs) until the outermost transaction ends. This ensures transactional isolation.
- After any write or transaction, the connection enters **sticky mode** — all subsequent operations route to primary for the remainder of the request lifecycle
- `ensureConnectedToPrimary(): void` — explicit override for read-after-write scenarios (e.g., sequence generators; ref: Doctrine ORM issue #9225)
- `ensureConnectedToReplica(): void` — explicit reset to replica (use with caution — only outside transactions)

**Transaction invariant:** Within a `beginTransaction()`...`commit()`/`rollBack()` block, ALL operations go to the primary. Period. This prevents stale reads, ensures transactional isolation, and avoids the Doctrine ORM issue #9225 where sequence reads fail on read-only replicas.

Read replicas are selected via round-robin or random strategy (configurable).

## Alternatives Considered

1. **Manual connection selection** — `$manager->get('read')` / `$manager->get('write')`. Rejected: leaks infrastructure concerns into application code.
2. **SQL parsing** — Parse each query to detect SELECT vs INSERT. Rejected: expensive, fragile (CTEs, stored procedures), false positives.
3. **Always primary** — No splitting. Rejected: doesn't address the common scaling need.

## Consequences

**Positive:**
- Transparent to consumer code — same `Connection` interface
- Sticky mode prevents stale reads after writes
- Configurable strategy per connection

**Negative:**
- Sticky mode means most request lifecycles end up on primary after first write
- Replica lag is invisible — stale reads possible in non-sticky mode
