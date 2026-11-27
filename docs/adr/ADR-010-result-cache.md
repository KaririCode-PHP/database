# ADR-010 — Result Cache with PSR-6

| Field  | Value      |
|--------|------------|
| Status | Accepted   |
| Date   | 2026-03-08 |

## Context

Doctrine DBAL has `executeCacheQuery()` with `QueryCacheProfile` since v2.x — any SELECT can be cached with configurable TTL. Laravel has no DBAL-level cache. Our design v2 had zero caching, which is the single biggest gap versus Doctrine for read-heavy workloads.

## Decision

Add `executeCacheQuery(string $sql, array $params, array $types, CacheProfile $cache): Result` to the Connection contract. `CacheProfile` is a readonly VO holding `ttl`, `key` (optional), and `cache` (PSR-6 pool). On cache hit, `CachedResult` wraps the deserialized array. On miss, materializes `fetchAllAssociative()`, stores, and wraps.

Cache key auto-generation: `sha256(sql . serialize(params) . serialize(types))`. This avoids Doctrine's documented pitfall (issue #2391) where shared keys accumulate stale entries.

Bridge: uses `kariricode/cache` when available, otherwise any PSR-6 `CacheItemPoolInterface`.

## Alternatives Considered

1. **Middleware-based caching** — Cache at the middleware layer. Rejected: middleware cannot distinguish cacheable SELECTs from non-cacheable writes without SQL parsing.
2. **Query builder method** — `$qb->cache(300)->executeQuery()`. Rejected: not all queries go through QueryBuilder.
3. **No cache** — Let consumers cache manually. Rejected: Doctrine's 544M downloads prove this is a must-have.

## Consequences

**Positive:** Parity with Doctrine. Composable with `kariricode/cache`. Per-query TTL.
**Negative:** Cache invalidation is consumer's responsibility (no automatic invalidation on writes).
