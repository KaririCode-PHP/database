# Gap Analysis — KaririCode\Database + KaririCode\NoSQL vs Best-in-Class

**Date:** 2026-03-08
**Objective:** Identify every missing capability that prevents these two packages from being the best PHP data store abstraction on the market.

**Method:** Feature-by-feature comparison against Doctrine DBAL 4.4 (~45K LoC, 544M downloads), Laravel Illuminate\Database 12.x (~25K LoC), mongodb/mongodb PHP Library 1.x, phpredis/Predis, Elasticsearch PHP 9.x, and PHP-CMSIG/SEAL.

---

## PART 1: kariricode/database — SQL DBAL

### Capabilities Present (✅ Already in Design)

| # | Capability | Status |
|---|---|---|
| 1 | Two-layer Driver/Connection | ✅ |
| 2 | Middleware decoration pipeline | ✅ |
| 3 | Platform dialect abstraction (MySQL, PgSQL, SQLite) | ✅ |
| 4 | Savepoint-based nested transactions | ✅ |
| 5 | Read/Write splitting with sticky mode | ✅ |
| 6 | SQL Query Builder (entity-agnostic) | ✅ |
| 7 | Type system with custom registration | ✅ |
| 8 | Schema inspector (read-only) | ✅ |
| 9 | Semantic exception conversion | ✅ |
| 10 | Generator-backed lazy iteration | ✅ |
| 11 | Advisory lock convenience API | ✅ |
| 12 | Connection Manager (named, lazy) | ✅ |
| 13 | LoggingMiddleware, ProfilingMiddleware | ✅ |
| 14 | RetryMiddleware (transient failures) | ✅ |
| 15 | TenancyMiddleware (schema switching) | ✅ |

### Gaps — Missing to Be Best-in-Class

#### GAP-DB-01: Result Cache (P0 — Doctrine has it, we don't)

**What Doctrine offers:** `QueryCacheProfile` — any query can be cached via PSR-6 `CacheItemPoolInterface`. The cache key is auto-generated from SQL + params + types. Configurable TTL per query. Ref: Doctrine DBAL 4.4 docs — "Caching".

**What we're missing:** Zero result caching. Every `executeQuery()` hits the database.

**Fix:** Add `executeCacheQuery(string $sql, array $params, array $types, CacheProfile $cache): Result` to Connection contract. `CacheProfile` is a readonly VO with `ttl`, `key` (optional), and `cache` (PSR-6 `CacheItemPoolInterface`). The result set is materialized to array, serialized, and cached. On cache hit, a `CachedResult` wrapping the deserialized array is returned.

**Bridge:** Uses `kariricode/cache` when available, otherwise accepts any PSR-6 implementation.

**Files:** +2 (CacheProfile VO, CachedResult implementation)

---

#### GAP-DB-02: Version-Specific Platforms (P1 — Doctrine has, we partially have)

**What Doctrine offers:** MySQL → `MySQLPlatform`, `MySQL80Platform`, `MySQL84Platform`. MariaDB → `MariaDBPlatform`, `MariaDB1052Platform`, `MariaDB1060Platform`. PostgreSQL → `PostgreSQLPlatform`, `PostgreSQL120Platform`. Version detection is automatic from server version string.

**What we're missing:** One `MySqlPlatform` covering all MySQL/MariaDB versions. No version-specific feature detection (e.g., MySQL 8.0+ `WITH` CTEs, MariaDB 10.5+ `RETURNING`, PgSQL 12+ `GENERATED ALWAYS AS`).

**Fix:** Add `PlatformVersion` VO holding `(vendor, major, minor, patch)`. Each Platform constructor receives the version. Feature methods check the version:

```php
public function supportsReturning(): bool
{
    return match (true) {
        $this->version->isMariaDb() && $this->version->gte(10, 5) => true,
        $this->version->isPostgreSql() => true, // all supported versions
        default => false,
    };
}
```

No separate class per version — one class with version-aware conditionals. Avoids Doctrine's 2K-line AbstractPlatform with 10+ subclasses.

**Files:** +1 (PlatformVersion VO), modifications to 3 existing Platforms

---

#### GAP-DB-03: DSN Parser (P1 — Doctrine and Laravel have it)

**What Doctrine offers:** `DsnParser` — parses `mysql://user:pass@host:3306/dbname?charset=utf8mb4` into connection params. Supports custom scheme-to-driver mapping.

**What Laravel offers:** `DATABASE_URL` environment variable parsing with the same DSN format.

**What we're missing:** Only `ConnectionParams` VO with explicit properties. No URL-based configuration.

**Fix:** Add `DsnParser::parse(string $dsn): ConnectionParams`. Supports `mysql://`, `pgsql://`, `sqlite://` schemes. Follows the standard `driver://user:password@host:port/database?options` format (RFC 3986).

**Files:** +1 (DsnParser)

---

#### GAP-DB-04: Transaction Isolation Level Control (P1 — Doctrine has it)

**What Doctrine offers:** `Connection::setTransactionIsolation(TransactionIsolationLevel::SERIALIZABLE)`. Changes the isolation level for the current connection session.

**What we have:** The `TransactionIsolationLevel` enum exists, but no method on Connection uses it.

**Fix:** Add to Connection: `setTransactionIsolation(TransactionIsolationLevel $level): void` and `getTransactionIsolation(): TransactionIsolationLevel`. Platform generates the appropriate SQL: MySQL `SET TRANSACTION ISOLATION LEVEL SERIALIZABLE`, PostgreSQL `SET default_transaction_isolation TO 'serializable'`.

**Files:** 0 new files, modifications to Connection and Platforms

---

#### GAP-DB-05: Batch/Bulk Insert (P1 — Laravel has it, Doctrine doesn't at DBAL level)

**What Laravel offers:** `DB::table('users')->insert([['name' => 'A'], ['name' => 'B']])` — generates multi-row INSERT in a single statement.

**What we're missing:** Only `executeStatement()` with raw SQL. No bulk insert helper.

**Fix:** Add to QueryBuilder: `insert(string $table)->values(array $rows)->executeStatement(): int`. Generates `INSERT INTO table (col1, col2) VALUES (?, ?), (?, ?)` with automatic parameter binding. Platform handles max placeholder limits (MySQL: 65535 params; SQLite: 999 params) with automatic chunking.

**Files:** Modification to SqlQueryBuilder (+bulk insert support)

---

#### GAP-DB-06: Schema Diff / Comparator (P2 — Doctrine has full SchemaManager)

**What Doctrine offers:** Full `SchemaManager` that can diff two schemas and generate ALTER TABLE statements.

**What we have:** Read-only `SchemaInspector`. By design, DDL is ORM's responsibility.

**Decision:** Keep as non-goal. Document that the ORM is responsible for schema diff. However, add a `SchemaDiff` contract to `kariricode/contract` so the ORM and migration tools can implement it consistently.

**Files:** +1 contract interface (deferred to Contract V4.1)

---

#### GAP-DB-07: Event System (P2 — Doctrine has `postConnect`, Laravel has DB events)

**What Doctrine offers:** Event hooks on connection events (postConnect, onSchemaAlterTable, etc.) via `EventManager`.

**What Laravel offers:** `DB::listen()`, `QueryExecuted` events, `DatabaseBusy` monitoring events.

**What we have:** Middleware can observe queries, but no event dispatch for connection lifecycle.

**Fix:** Define connection event VOs: `ConnectionEstablished`, `QueryExecuted`, `TransactionBegun`, `TransactionCommitted`, `TransactionRolledBack`. The `EventDispatcherBridge` publishes these via `kariricode/event-dispatcher` when available. Bridge is duck-typed (callable), not a hard dependency.

**Files:** +5 event VOs, modification to EventDispatcherBridge

---

#### GAP-DB-08: PHP 8.4 PDO Subclasses (P2 — Doctrine 4.4 added this)

**What Doctrine 4.4 added:** Leverages PHP 8.4's `Pdo\Mysql`, `Pdo\Pgsql` subclasses (typed PDO objects). `getNativeConnection()` returns the specific subclass instead of generic `PDO`.

**What we need:** Our `PdoConnection::getNativeConnection()` should return `Pdo\Mysql|Pdo\Pgsql|Pdo\Sqlite` when running PHP 8.4+.

**Fix:** Use `new \Pdo\Mysql(...)` / `new \Pdo\Pgsql(...)` instead of `new PDO(...)` when available. This is a driver-level implementation detail, not a contract change.

**Files:** Modification to PdoMySqlDriver, PdoPgSqlDriver, PdoSqliteDriver

---

### Database Summary

| Priority | Gaps | Impact |
|----------|:----:|--------|
| P0 | 1 | Result cache — most requested feature after basics |
| P1 | 4 | Version platforms, DSN parser, isolation levels, bulk insert |
| P2 | 3 | Schema diff (deferred), events, PHP 8.4 PDO subclasses |
| **Total** | **8** | **+9 new files, modifications to ~10 existing** |

---

## PART 2: kariricode/nosql — Non-Relational Stores

### Capabilities Present (✅ Already in Design)

| # | Capability | Status |
|---|---|---|
| 1 | Three-family architecture (Document, KeyValue, Search) | ✅ |
| 2 | MongoDB CRUD (insertOne, find, updateOne, deleteOne) | ✅ |
| 3 | MongoDB aggregation pipeline builder | ✅ |
| 4 | MongoDB change streams (watch) | ✅ |
| 5 | Redis all data structures (string, hash, list, set, zset) | ✅ |
| 6 | Redis MULTI/EXEC transactions | ✅ |
| 7 | Redis Pub/Sub | ✅ |
| 8 | Redis Lua scripting | ✅ |
| 9 | Dual Redis drivers (phpredis + Predis) | ✅ |
| 10 | Elasticsearch search/index/bulk | ✅ |
| 11 | Search DSL query builder | ✅ |
| 12 | Shared middleware pipeline | ✅ |
| 13 | Semantic exceptions per family | ✅ |
| 14 | Connection lifecycle (lazy, retry) | ✅ |

### Gaps — Missing to Be Best-in-Class

#### GAP-NS-01: MongoDB GridFS (P1 — Official library has it)

**What mongodb/mongodb offers:** `MongoDB\GridFS\Bucket` — store and retrieve large files (>16MB BSON limit) across chunks. API: `openUploadStream()`, `openDownloadStream()`, `find()`, `delete()`.

**What we're missing:** Zero GridFS support.

**Fix:** Add `GridFsBucket` contract and implementation wrapping `MongoDB\GridFS\Bucket`. Methods: `uploadFromStream(string $filename, resource $source, array $options): ObjectId`, `openDownloadStream(ObjectId $id): resource`, `find(array $filter): DocumentCursor`, `delete(ObjectId $id): void`, `drop(): void`.

**Files:** +2 (contract + implementation)

---

#### GAP-NS-02: MongoDB Client-Side Field Level Encryption (P2 — Official driver supports)

**What ext-mongodb supports:** Automatic client-side encryption/decryption using `MongoDB\Driver\ClientEncryption`. Encrypts fields before they leave the PHP process.

**Decision:** Defer to Phase 3. Encryption is a specialized feature that requires libmongocrypt and KMS integration. Document as future capability.

---

#### GAP-NS-03: Redis Streams (P0 — phpredis/Predis support, we don't list)

**What phpredis offers:** `XADD`, `XREAD`, `XRANGE`, `XLEN`, `XACK`, `XGROUP CREATE`, `XREADGROUP`, `XPENDING`, `XCLAIM`, `XTRIM`. Redis Streams are the backbone of event sourcing and message queuing.

**What we have:** `KeyValueClient` contract lists strings, hashes, lists, sets, sorted sets — but NOT streams.

**Fix:** Add stream operations to `KeyValueClient`:
- `xAdd(string $key, string $id, array $fields): string`
- `xRead(array $streams, ?int $count, ?int $block): array`
- `xRange(string $key, string $start, string $end, ?int $count): array`
- `xLen(string $key): int`
- `xAck(string $key, string $group, string ...$ids): int`
- `xGroupCreate(string $key, string $group, string $id): bool`
- `xReadGroup(string $group, string $consumer, array $streams, ?int $count, ?int $block): array`
- `xPending(string $key, string $group): array`
- `xClaim(string $key, string $group, string $consumer, int $minIdleTime, string ...$ids): array`
- `xTrim(string $key, int $maxLen): int`

Redis Streams are critical for `kariricode/queue` integration (Queue V4.0 can use Redis streams as a backend).

**Files:** Modification to KeyValueClient contract, +1 stream helper class

---

#### GAP-NS-04: Redis Cluster / Sentinel Support (P1 — Production requirement)

**What phpredis offers:** `RedisCluster` class with `new RedisCluster(null, ['host:port', ...])`. Handles slot distribution, MOVED/ASK redirections, and failover automatically.

**What Predis offers:** `$client = new Predis\Client(['tcp://node1', 'tcp://node2'], ['cluster' => 'redis'])`.

**What we're missing:** `KeyValueConnectionParams` has `host` + `port` (single node). No cluster or Sentinel configuration.

**Fix:** Extend `KeyValueConnectionParams`:
- Add `nodes: array` (for cluster: `['host1:port1', 'host2:port2']`)
- Add `sentinel: ?SentinelConfig` (VO: `sentinels`, `service`, `password`)
- Add `cluster: bool` (enables cluster mode)

Driver auto-detects: single node → `Redis`, cluster → `RedisCluster`. Predis handles this via `'cluster' => 'redis'` option.

**Files:** +1 (SentinelConfig VO), modification to KeyValueConnectionParams and both drivers

---

#### GAP-NS-05: Redis Connection Pooling / Persistent Connections (P1)

**What phpredis offers:** `pconnect()` for persistent connections that survive PHP request lifecycle (essential for PHP-FPM).

**What Laravel offers:** `'persistent' => true` in Redis config.

**What we have:** `KeyValueConnectionParams` has `persistent` field — but no explicit reconnection strategy.

**Fix:** Add `reconnectOnFailure: bool` and `retryInterval: int` to `KeyValueConnectionParams`. The `PhpRedisDriver` uses `pconnect()` when `persistent` is true. Add `timeout`, `readTimeout`, `retryInterval` configuration per phpredis/Predis APIs.

**Files:** Modification to KeyValueConnectionParams and drivers

---

#### GAP-NS-06: Elasticsearch Index Templates / Component Templates (P1)

**What Elasticsearch offers:** Index templates auto-apply mappings and settings when new indices matching a pattern are created. Essential for time-series data (`logs-2026.03.*`).

**What we have:** `SearchIndex::create(mapping)` and `putMapping()` — but no template management.

**Fix:** Add to `SearchClient`:
- `putIndexTemplate(string $name, array $body): void`
- `getIndexTemplate(string $name): array`
- `deleteIndexTemplate(string $name): void`
- `putComponentTemplate(string $name, array $body): void`

**Files:** Modification to SearchClient contract and implementations

---

#### GAP-NS-07: Elasticsearch Aliases (P1 — Critical for zero-downtime reindexing)

**What Elasticsearch offers:** Index aliases allow pointing `articles` to `articles_v2` without changing application code. Essential for zero-downtime reindexing.

**What we're missing:** No alias management.

**Fix:** Add to `SearchClient`:
- `putAlias(string $index, string $alias): void`
- `deleteAlias(string $index, string $alias): void`
- `getAliases(?string $index): array`
- `updateAliases(array $actions): void` — atomic swap (add + remove in one call)

**Files:** Modification to SearchClient contract and implementations

---

#### GAP-NS-08: Elasticsearch Async Bulk with Backpressure (P2)

**What the official client offers:** Bulk helper that auto-flushes when a size/count threshold is reached.

**What we have:** `SearchClient::bulk(array)` — synchronous, consumer manages batching.

**Fix:** Add `BulkAccumulator` helper class:
- `add(string $action, string $index, string $id, ?array $body): self`
- `flush(): BulkResult`
- Constructor accepts `maxActions: int`, `maxSizeBytes: int`
- Auto-flushes when threshold reached
- `__destruct()` flushes remaining

**Files:** +1 (BulkAccumulator)

---

#### GAP-NS-09: BSON Type Mapping Customization (P1 — mongodb/mongodb has TypeMap)

**What mongodb/mongodb offers:** `TypeMap` — configures how BSON types map to PHP types. `['root' => 'array', 'document' => 'array', 'array' => 'array']` returns pure arrays. Can map to custom classes.

**What we're missing:** No explicit TypeMap configuration in contracts.

**Fix:** Add `typeMap: array` to `DocumentConnectionParams` and as optional parameter to `find()`, `findOne()`, `aggregate()`. Default: `['root' => 'array', 'document' => 'array']` (pure PHP arrays, no `BSONDocument` objects leaking to consumers).

**Files:** Modification to DocumentConnectionParams and collection methods

---

#### GAP-NS-10: Cross-Family Health Check / Ping (P2)

**What's needed:** A unified health check endpoint for all configured stores. Kubernetes readiness probes need this.

**Fix:** `NoSqlConnectionManager::healthCheck(): array<string, bool>` — pings all registered connections and returns status map. Individual: `$manager->ping('redis'): bool`.

**Files:** Modification to NoSqlConnectionManager

---

### NoSQL Summary

| Priority | Gaps | Impact |
|----------|:----:|--------|
| P0 | 1 | Redis Streams — critical for Queue integration |
| P1 | 6 | GridFS, Cluster/Sentinel, persistent connections, ES templates, ES aliases, BSON TypeMap |
| P2 | 3 | Client-side encryption (deferred), bulk accumulator, health check |
| **Total** | **10** | **+6 new files, modifications to ~8 existing** |

---

## PART 3: Cross-Package Capabilities

### GAP-CROSS-01: Unified Health Check (P1)

Both `kariricode/database` and `kariricode/nosql` should expose a `ping(): bool` on their ConnectionManager. A future `kariricode/health` package (or the Framework's `HealthEndpoint`) aggregates these.

### GAP-CROSS-02: Unified Connection Events (P2)

Both packages should emit connection lifecycle events (`Connected`, `Disconnected`, `QueryExecuted`, `OperationExecuted`) that flow into `kariricode/observability` via the same bridge pattern. The event VOs should share a common `DataStoreEvent` base contract.

### GAP-CROSS-03: PHP 8.4 Fiber Integration (P2 — Future)

With `kariricode/async` EventLoop, database and NoSQL connections could support Fiber-based non-blocking I/O. Not for Phase 1, but the middleware architecture already supports this — a `FiberMiddleware` could wrap blocking calls in Fibers.

---

## CONSOLIDATED PRIORITY TABLE

| Priority | Database Gaps | NoSQL Gaps | Total |
|----------|:---:|:---:|:---:|
| P0 | 1 (result cache) | 1 (Redis streams) | **2** |
| P1 | 4 | 6 | **10** |
| P2 | 3 | 3 | **6** |
| Cross-pkg | — | — | **3** |
| **Total** | **8** | **10** | **21 gaps** |

---

## FINAL SCORECARD — After Fixing All Gaps

| Capability | Doctrine DBAL 4.4 | Laravel DB 12 | KaririCode DB (v3) | KaririCode NoSQL (v2) |
|---|:---:|:---:|:---:|:---:|
| **SQL — Connection** | | | | |
| Named connections | ❌ (external) | ✅ | ✅ | — |
| Read/write splitting | ✅ | ✅ | ✅ | — |
| Lazy connection | ✅ | ✅ | ✅ | ✅ |
| DSN parser | ✅ | ✅ | ✅ (+GAP-03) | — |
| Middleware pipeline | ✅ | ❌ (events) | ✅ | ✅ |
| Result caching | ✅ (PSR-6) | ❌ | ✅ (+GAP-01) | — |
| **SQL — Transactions** | | | | |
| Savepoint nesting | ✅ | ✅ (silent) | ✅ (explicit) | — |
| Isolation level control | ✅ | ✅ | ✅ (+GAP-04) | — |
| Advisory locks | ❌ | ❌ | ✅ | — |
| **SQL — Query** | | | | |
| Query builder | ✅ | ✅ | ✅ | — |
| Bulk insert | ❌ (DBAL) | ✅ | ✅ (+GAP-05) | — |
| Generator iteration | ❌ | ❌ | ✅ | — |
| **SQL — Platform** | | | | |
| Version-specific | ✅ (10+ classes) | ❌ | ✅ (+GAP-02) | — |
| MySQL/PgSQL/SQLite | ✅ | ✅ | ✅ | — |
| MariaDB specific | ✅ | ❌ | ✅ (+GAP-02) | — |
| **SQL — Types** | | | | |
| Custom type registration | ✅ | ❌ (model-level) | ✅ | — |
| Platform-aware SQL | ✅ | ❌ | ✅ | — |
| BCMath\Number (8.4) | ✅ (DBAL 4.4) | ❌ | ❌ (add to Phase 2) | — |
| **SQL — Schema** | | | | |
| Read-only introspection | ✅ | ✅ | ✅ | — |
| Schema diff / DDL | ✅ | ✅ | ❌ (ORM) | — |
| Schema-qualified queries | ❌ | ❌ | ✅ | — |
| **SQL — Exceptions** | | | | |
| Semantic conversion | ✅ | ❌ (generic) | ✅ | — |
| Error code ranges | ❌ | ❌ | ✅ | — |
| **SQL — Modern PHP** | | | | |
| PHP 8.4+ required | ❌ (8.1+) | ❌ (8.2+) | ✅ | ✅ |
| PDO subclasses (8.4) | ✅ (4.4) | ❌ | ✅ (+GAP-08) | — |
| readonly VOs | Partial | ❌ | ✅ | ✅ |
| final classes | Partial | ❌ | ✅ | ✅ |
| **NoSQL — MongoDB** | | | | |
| CRUD | — | — | — | ✅ |
| Aggregation pipeline | — | — | — | ✅ |
| Change streams | — | — | — | ✅ |
| GridFS | — | — | — | ✅ (+GAP-01) |
| BSON TypeMap config | — | — | — | ✅ (+GAP-09) |
| **NoSQL — Redis** | | | | |
| All data structures | — | — | — | ✅ |
| Streams (XADD/XREAD) | — | — | — | ✅ (+GAP-03) |
| Cluster / Sentinel | — | — | — | ✅ (+GAP-04) |
| Persistent connections | — | — | — | ✅ (+GAP-05) |
| Dual drivers | — | — | — | ✅ |
| Transactions | — | — | — | ✅ |
| Pub/Sub | — | — | — | ✅ |
| **NoSQL — Search** | | | | |
| Search / Index / Bulk | — | — | — | ✅ |
| DSL query builder | — | — | — | ✅ |
| Index templates | — | — | — | ✅ (+GAP-06) |
| Aliases (zero-downtime) | — | — | — | ✅ (+GAP-07) |
| Bulk accumulator | — | — | — | ✅ (+GAP-08) |
| **Cross-cutting** | | | | |
| Ecosystem middleware | ❌ (standalone) | ❌ (framework) | ✅ | ✅ |
| Zero hard deps | ❌ (psr/log, psr/cache) | ❌ (illuminate/*) | ✅ | ✅ |
| ARFA conformance | N/A | N/A | ✅ L2 | ✅ L2 |
| Health check / ping | ❌ | ❌ | ✅ (+CROSS-01) | ✅ (+CROSS-01) |

**After fixing all 21 gaps, both packages would have:**
- Every feature Doctrine DBAL offers + generator iteration + advisory locks + schema-qualified inspection + named connections + ecosystem middleware
- Every feature Laravel DB offers + semantic exceptions + middleware pipeline + type system + bulk insert
- MongoDB, Redis, and Elasticsearch behind a unified lifecycle with middleware — something no PHP package currently offers
- The only PHP data store abstraction built on PHP 8.4+ with readonly VOs, final classes, and zero external dependencies beyond contracts

---

## References

- Doctrine DBAL 4.4: Architecture, Platforms, Types, Caching, Query Builder, PrimaryReadReplicaConnection
- Laravel 12.x: Database, Redis, Eloquent
- mongodb/mongodb: GridFS, TypeMap, Change Streams, Tailable Cursors
- phpredis: Redis Streams, RedisCluster, Sentinel
- Predis 3.x: Cluster, Replication, Transactions
- Elasticsearch PHP 9.x: Bulk, Templates, Aliases, Scroll
- PHP-CMSIG/SEAL: Multi-engine search abstraction
- Sadalage & Fowler (2012): *NoSQL Distilled*
- Fowler (2002): *Patterns of Enterprise Application Architecture*
