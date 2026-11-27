# SPEC-001 — KaririCode\Database v3 (All Gaps Closed)

| Field             | Value                                    |
|-------------------|------------------------------------------|
| Status            | Final Draft                              |
| Date              | 2026-03-08                               |
| ARFA Version      | 1.3                                      |
| Conformance Level | 2 (Behavioral)                           |
| Error Code Range  | DB-6000–6199                             |
| Revision          | v3 — 14 review issues + 8 best-in-class gaps closed |

---

## §1 Purpose and Scope

SQL-only DBAL foundation layer. Replaces duplicated PDO wrappers in ORM, DataFlow, and Tenancy. Explicit SQL-relational scope — NoSQL is `kariricode/nosql`.

## §2 Contract Catalog (Final — All Gaps Closed)

### §2.1 Core Contracts (12 interfaces — local Phase 1, promoted to kariricode/contract Phase 2)

**Connection** — the primary consumer-facing API:
- `executeQuery(string $sql, array $params = [], array $types = []): Result`
- `executeCacheQuery(string $sql, array $params, array $types, CacheProfile $cache): Result` ← **GAP-DB-01**
- `executeStatement(string $sql, array $params = [], array $types = []): int`
- `beginTransaction(): void`
- `commit(): void`
- `rollBack(): void`
- `transactional(callable $callback): mixed`
- `createSavepoint(string $name): void`
- `releaseSavepoint(string $name): void`
- `rollBackSavepoint(string $name): void`
- `isTransactionActive(): bool`
- `transactionNestingLevel(): int`
- `setTransactionIsolation(TransactionIsolationLevel $level): void` ← **GAP-DB-04**
- `transactionIsolation(): TransactionIsolationLevel` ← **GAP-DB-04**
- `advisoryLock(int $key): void`
- `tryAdvisoryLock(int $key): bool`
- `platform: Platform`
- `getConfiguration(): Configuration`
- `nativeConnection(): object`
- `createQueryBuilder(): QueryBuilder`
- `ping(): bool` ← **GAP-CROSS-01**
- `lastInsertId(?string $name = null): string`

**Result** — query result abstraction:
- `fetchAssociative(): array|false`
- `fetchNumeric(): array|false`
- `fetchOne(): mixed`
- `fetchAllAssociative(): array`
- `fetchAllNumeric(): array`
- `fetchFirstColumn(): array`
- `iterateAssociative(): \Generator<int, array<string, mixed>>`
- `iterateNumeric(): \Generator<int, array<int, mixed>>`
- `iterateColumn(int $index = 0): \Generator<int, mixed>`
- `rowCount(): int`
- `columnCount(): int`
- `free(): void`

**QueryBuilder** — entity-agnostic SQL builder (ref: Doctrine DBAL 4.4):
- `select(string ...$columns): self`
- `from(string $table, ?string $alias = null): self`
- `insert(string $table): self`
- `update(string $table, ?string $alias = null): self`
- `delete(string $table, ?string $alias = null): self`
- `values(array $values): self` — single-row INSERT values
- `bulkValues(array $rows): self` — multi-row INSERT with auto-chunking ← **GAP-DB-05**
- `set(string $column, string $value): self` — UPDATE SET
- `where(string $predicate): self`
- `andWhere(string $predicate): self`
- `orWhere(string $predicate): self`
- `innerJoin(string $fromAlias, string $table, string $alias, string $condition): self`
- `leftJoin(string $fromAlias, string $table, string $alias, string $condition): self`
- `groupBy(string ...$columns): self`
- `addGroupBy(string ...$columns): self`
- `having(string $predicate): self`
- `andHaving(string $predicate): self`
- `orderBy(string $column, string $direction = 'ASC'): self`
- `addOrderBy(string $column, string $direction = 'ASC'): self`
- `setMaxResults(?int $limit): self`
- `setFirstResult(int $offset): self`
- `setParameter(int|string $key, mixed $value, ?int $type = null): self`
- `sql(): string`
- `parameters(): array`
- `parameterTypes(): array`
- `executeQuery(): Result`
- `executeStatement(): int`

**Platform** — dialect abstraction with version awareness:
- `quoteIdentifier(string $identifier): string`
- `getLimitSql(string $query, ?int $limit, ?int $offset): string`
- `currentTimestampSql(): string`
- `getUpsertSql(string $table, array $columns, array $conflictColumns): string`
- `getBulkInsertSql(string $table, array $columns, int $rowCount): string` ← **GAP-DB-05**
- `maxPlaceholders(): int` ← **GAP-DB-05** (MySQL: 65535, SQLite: 999, PgSQL: 34464)
- `supportsReturning(): bool`
- `supportsSavepoints(): bool`
- `supportsSchemas(): bool`
- `supportsAdvisoryLocks(): bool`
- `supportsCommonTableExpressions(): bool` ← **GAP-DB-02**
- `supportsJsonOperations(): bool` ← **GAP-DB-02**
- `getAdvisoryLockSql(int $key): string`
- `getSetSchemaPathSql(string $schema): string`
- `setTransactionIsolationSql(TransactionIsolationLevel $level): string` ← **GAP-DB-04**
- `getBooleanLiteral(bool $value): string`
- `version: PlatformVersion` ← **GAP-DB-02**

**Other contracts:** `Driver`, `DriverConnection`, `Statement`, `TypeConverter`, `SchemaInspector`, `Middleware`, `ExceptionConverter` — unchanged from v2.

### §2.2 New Contracts (Gap Fixes)

**CacheProfile** (readonly VO) ← **GAP-DB-01**:
- `ttl: int` (seconds)
- `key: ?string` (null = auto-generate from SQL+params hash)
- `cache: ?object` (PSR-6 CacheItemPoolInterface, null = use connection's default)

**CachedResult** (implements Result) ← **GAP-DB-01**:
- Wraps `array<int, array>` materialized from cache
- All `fetch*` methods operate on the in-memory array
- `iterate*` generators yield from the array
- `free()` releases the array reference

**Configuration** (readonly VO):
- `resultCache: ?object` (PSR-6 pool, default for all queries)
- `middlewares: array<Middleware>`
- `autoCommit: bool` (default true)

**PlatformVersion** (readonly VO) ← **GAP-DB-02**:
- `vendor: string` ('mysql', 'mariadb', 'postgresql', 'sqlite')
- `major: int`, `minor: int`, `patch: int`
- `isMySQL(): bool`, `isMariaDb(): bool`, `isPostgreSql(): bool`, `isSqlite(): bool`
- `gte(int $major, int $minor = 0): bool`

**DsnParser** ← **GAP-DB-03**:
- `parse(string $dsn): ConnectionParams`
- Supports `mysql://`, `pgsql://`, `sqlite://`, `mariadb://` schemes
- Follows RFC 3986 URI syntax: `driver://user:password@host:port/database?charset=utf8mb4`
- Custom scheme mapping: `new DsnParser(['custom' => 'pdo_mysql'])`

### §2.3 Event VOs ← **GAP-DB-07**

| Event VO | Properties | Emitted By |
|---|---|---|
| `ConnectionEstablished` | `connectionName`, `driverType`, `durationMs` | ConnectionFactory |
| `QueryExecuted` | `sql`, `params`, `types`, `executionTimeMs`, `rowCount` | Connection |
| `TransactionBegun` | `nestingLevel` | Connection |
| `TransactionCommitted` | `nestingLevel` | Connection |
| `TransactionRolledBack` | `nestingLevel`, `toSavepoint` | Connection |
| `ConnectionError` | `exception`, `sql`, `params` | Connection |

Published via `EventDispatcherBridge` — duck-typed callable `(object $event): void`. Zero hard dependency on `kariricode/event-dispatcher`.

### §2.4 Enums (Updated)

| Enum | Type | Values | Notes |
|---|---|---|---|
| `DriverType` | `string` | `pdo_mysql`, `pdo_pgsql`, `pdo_sqlite` | E-003 justified |
| `ParameterType` | `int` | `NULL=0`, `INTEGER=1`, `STRING=2`, `BINARY=3`, `BOOLEAN=5`, `ASCII=17` | PDO::PARAM_* compat |
| `TransactionIsolationLevel` | `int` | `READ_UNCOMMITTED=1`, `READ_COMMITTED=2`, `REPEATABLE_READ=3`, `SERIALIZABLE=4` | Now consumed by Connection ← **GAP-DB-04** |
| `FetchMode` | Pure | `ASSOCIATIVE`, `NUMERIC`, `FIRST_COLUMN`, `KEY_VALUE` | — |
| `ColumnType` | Pure | 16 values (INTEGER through UUID) | — |
| `ReferentialAction` | `string` | `CASCADE`, `SET_NULL`, `SET_DEFAULT`, `RESTRICT`, `NO_ACTION` | E-003 justified |

## §3 Driver Layer

Phase 1: PDO-only (3 drivers). PHP 8.4 PDO subclasses used ← **GAP-DB-08**:

```php
// PdoMySqlDriver uses Pdo\Mysql (PHP 8.4) instead of generic PDO
$pdo = new \Pdo\Mysql($dsn, $user, $password, $options);
// nativeConnection() returns Pdo\Mysql, not PDO
```

Version detection: Driver queries `SELECT VERSION()` (MySQL/MariaDB) or `SHOW server_version` (PgSQL) or `sqlite_version()` on first connect. Result parsed into `PlatformVersion` VO.

### §3.1 Exception Conversion

`AbstractExceptionConverter` handles standard SQLSTATE codes. Per-driver converters add vendor-specific codes. Unchanged from v2.

## §4 Connection Layer

### §4.1 Result Cache ← **GAP-DB-01**

```php
// Per-query caching
$result = $conn->executeCacheQuery(
    'SELECT * FROM users WHERE status = ?',
    ['active'],
    [ParameterType::STRING],
    new CacheProfile(ttl: 300, key: 'active_users'),
);

// Default cache for all queries (via Configuration)
$config = new Configuration(resultCache: $psr6Pool);
```

Cache key auto-generation: `sha256(sql . serialize(params) . serialize(types))`. On cache hit, returns `CachedResult` wrapping the deserialized array. On miss, executes query, materializes result via `fetchAllAssociative()`, stores in cache, returns `CachedResult`.

Ref: Doctrine DBAL 4.4 — "Caching": `executeCacheQuery()` with `QueryCacheProfile` providing TTL and PSR-6 pool.

### §4.2 Transaction Isolation ← **GAP-DB-04**

```php
$conn->setTransactionIsolation(TransactionIsolationLevel::SERIALIZABLE);
// MySQL: SET TRANSACTION ISOLATION LEVEL SERIALIZABLE
// PgSQL: SET default_transaction_isolation TO 'serializable'
$conn->beginTransaction();
// ... all queries run at SERIALIZABLE level
$conn->commit();
```

### §4.3 ReadWriteConnection

Unchanged from v2 — `beginTransaction()` forces primary-only, sticky mode after first write.

### §4.4 Health Check ← **GAP-CROSS-01**

```php
$conn->ping(): bool  // executes platform-specific ping (SELECT 1)
$manager->healthCheck(): array<string, bool>  // pings all named connections
```

## §5 Query Builder (Updated with Bulk Insert) ← **GAP-DB-05**

### §5.1 Bulk Insert

```php
$qb = $conn->createQueryBuilder();
$affected = $qb
    ->insert('users')
    ->bulkValues([
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        ['name' => 'Bob', 'email' => 'bob@example.com'],
        // ... 10,000 rows
    ])
    ->executeStatement();
// Generates: INSERT INTO users (name, email) VALUES (?, ?), (?, ?), ...
// Auto-chunks by Platform::maxPlaceholders() (MySQL: 65535, SQLite: 999)
```

The Platform determines the maximum placeholder count per statement. `bulkValues()` auto-splits into multiple INSERT statements if the row count × column count exceeds the limit. Each chunk executes within the current transaction.

## §6 Platform Layer (Updated with Version Awareness) ← **GAP-DB-02**

```php
$platform = $conn->platform;
$version = $platform->version;
// PlatformVersion { vendor: 'mariadb', major: 10, minor: 11, patch: 6 }

$platform->supportsReturning();              // true (MariaDB 10.5+)
$platform->supportsCommonTableExpressions(); // true (MySQL 8.0+, all PgSQL, SQLite 3.8.3+)
$platform->supportsJsonOperations();         // true (MySQL 5.7+, PgSQL 9.4+, SQLite 3.38+)
```

No separate Platform class per version — one class with conditional checks on `PlatformVersion`. Avoids Doctrine's 10-class inheritance chain.

## §7 DSN Parser ← **GAP-DB-03**

```php
use KaririCode\Database\DsnParser;

$parser = new DsnParser();
$params = $parser->parse('mysql://root:secret@localhost:3306/myapp?charset=utf8mb4');
// ConnectionParams { driver: 'pdo_mysql', host: 'localhost', port: 3306, database: 'myapp', ... }

// Custom scheme mapping
$parser = new DsnParser(['mariadb' => 'pdo_mysql', 'cockroach' => 'pdo_pgsql']);
$params = $parser->parse('cockroach://user:pass@crdb:26257/mydb');
```

## §8 Connection Events ← **GAP-DB-07, GAP-CROSS-02**

```php
// Bridge to kariricode/event-dispatcher (optional, duck-typed)
$factory = new ConnectionFactory(
    eventDispatcher: function (object $event): void {
        // $event is ConnectionEstablished | QueryExecuted | TransactionBegun | ...
        $this->logger->info($event::class, (array) $event);
    },
);
```

Events are readonly VOs. All implement a common `DataStoreEvent` marker interface (shared with `kariricode/nosql` for unified observability).

## §9 File Inventory (Final — v3)

```
src/
├── Contract/                        # 12 interfaces
│   ├── Connection.php
│   ├── Driver.php
│   ├── DriverConnection.php
│   ├── Statement.php
│   ├── Result.php
│   ├── Platform.php
│   ├── TypeConverter.php
│   ├── SchemaInspector.php
│   ├── QueryBuilder.php
│   ├── Middleware.php
│   ├── ExceptionConverter.php
│   └── DataStoreEvent.php           # shared marker with NoSQL
├── Driver/                          # 6 files
│   ├── PdoMySqlDriver.php           # uses Pdo\Mysql (PHP 8.4)
│   ├── PdoPgSqlDriver.php           # uses Pdo\Pgsql (PHP 8.4)
│   ├── PdoSqliteDriver.php          # uses Pdo\Sqlite (PHP 8.4)
│   └── Pdo/
│       ├── PdoConnection.php
│       ├── PdoStatement.php
│       └── PdoResult.php
├── Driver/ExceptionConverter/       # 4 files
│   ├── AbstractExceptionConverter.php
│   ├── MySqlExceptionConverter.php
│   ├── PostgreSqlExceptionConverter.php
│   └── SqliteExceptionConverter.php
├── Connection/                      # 5 files
│   ├── DefaultConnection.php
│   ├── ReadWriteConnection.php
│   ├── ConnectionRegistry.php
│   ├── ConnectionFactory.php
│   └── Configuration.php
├── Cache/                           # 2 files  ← GAP-DB-01
│   ├── CacheProfile.php
│   └── CachedResult.php
├── Query/                           # 3 files
│   ├── SqlQueryBuilder.php          # includes bulkValues() ← GAP-DB-05
│   ├── ExpressionBuilder.php
│   └── CompositeExpression.php
├── Middleware/                       # 4 files
│   ├── LoggingMiddleware.php
│   ├── ProfilingMiddleware.php
│   ├── TenancyMiddleware.php
│   └── RetryMiddleware.php
├── Platform/                        # 3 files  (version-aware) ← GAP-DB-02
│   ├── MySqlPlatform.php
│   ├── PostgreSqlPlatform.php
│   └── SqlitePlatform.php
├── Type/                            # 14 files
│   ├── TypeRegistry.php
│   └── ... (13 type converters)
├── Schema/                          # 3 files
│   ├── MySqlSchemaInspector.php
│   ├── PostgreSqlSchemaInspector.php
│   └── SqliteSchemaInspector.php
├── ValueObject/                     # 9 files  (+3 new)
│   ├── ConnectionParams.php
│   ├── PlatformVersion.php          # ← GAP-DB-02
│   ├── CacheProfile.php             # ← GAP-DB-01 (alias, actual in Cache/)
│   ├── ColumnMetadata.php
│   ├── IndexMetadata.php
│   ├── ForeignKeyMetadata.php
│   ├── QueryResult.php
│   └── TableMetadata.php
├── Event/                           # 6 files  ← GAP-DB-07
│   ├── ConnectionEstablished.php
│   ├── QueryExecuted.php
│   ├── TransactionBegun.php
│   ├── TransactionCommitted.php
│   ├── TransactionRolledBack.php
│   └── ConnectionError.php
├── Enum/                            # 6 files
│   ├── DriverType.php
│   ├── ParameterType.php
│   ├── TransactionIsolationLevel.php
│   ├── FetchMode.php
│   ├── ColumnType.php
│   └── ReferentialAction.php
├── Exception/                       # 7 files
│   ├── DatabaseException.php
│   ├── ConnectionException.php
│   ├── QueryException.php
│   ├── TransactionException.php
│   ├── PlatformException.php
│   ├── SchemaException.php
│   └── TypeConversionException.php
├── Bridge/                          # 3 files
│   ├── LoggingBridge.php
│   ├── ObservabilityBridge.php
│   └── EventDispatcherBridge.php
└── DsnParser.php                    # ← GAP-DB-03
```

**Final totals: ~85 source files, ~8,500–10,000 LoC**

## §10 ARFA 1.3 Errata Compliance

All 12 errata pass. 25 semantic exception factories. 4 backed enums with E-003 justification. Level 2 conformance in `composer.json`.

## §11 Gap Closure Verification

| Gap | Description | Status | Section |
|---|---|---|---|
| DB-01 | Result cache (PSR-6) | ✅ Closed | §4.1 |
| DB-02 | Version-specific platforms | ✅ Closed | §6 |
| DB-03 | DSN parser | ✅ Closed | §7 |
| DB-04 | Transaction isolation level | ✅ Closed | §4.2 |
| DB-05 | Bulk insert | ✅ Closed | §5.1 |
| DB-07 | Connection events | ✅ Closed | §8 |
| DB-08 | PHP 8.4 PDO subclasses | ✅ Closed | §3 |
| CROSS-01 | Health check / ping | ✅ Closed | §4.4 |
| CROSS-02 | Unified DataStoreEvent | ✅ Closed | §8 |

## §12 Error Codes and Ecosystem Registry

Range **6000–6199**. Generated from `src/Exception`; `ExceptionConformanceTest` enforces uniqueness, range, prefix and family.

| Code | Exception | Factory | Family | Message |
|---|---|---|---|---|
| 6010 | `ConnectionException` | `connectionFailed()` | Runtime | Could not connect to the database server. |
| 6011 | `ConnectionException` | `connectionLost()` | Runtime | The connection to the database server was lost. |
| 6012 | `ConfigurationException` | `authenticationFailed()` | Configuration | The database server rejected the configured credentials. |
| 6013 | `ConfigurationException` | `databaseNotFound()` | Configuration | The configured database does not exist on the server. |
| 6014 | `ConnectionException` | `connectionTimeout()` | Runtime | Connecting to the database server timed out. |
| 6015 | `ConnectionException` | `tooManyConnections()` | Runtime | The database server refused the connection: too many connections. |
| 6016 | `ConfigurationException` | `invalidDsn()` | Configuration | Invalid DSN: the value could not be parsed as a URI. |
| 6017 | `ConfigurationException` | `unknownDsnScheme()` | Configuration | Unknown DSN scheme. |
| 6018 | `ConfigurationException` | `connectionNotConfigured()` | Configuration | No connection is configured under the requested name. |
| 6019 | `ConfigurationException` | `invalidCharset()` | Configuration | Invalid charset: only letters, digits, hyphen and underscore are allowed. |
| 6020 | `ConfigurationException` | `unknownDriver()` | Configuration | Unknown driver: the supported drivers are pdo_mysql, pdo_pgsql and pdo_sqlite. |
| 6030 | `QueryException` | `syntaxError()` | Runtime | The statement has a syntax error or violates an access rule. |
| 6031 | `QueryException` | `constraintViolation()` | Runtime | An integrity constraint was violated. |
| 6032 | `QueryException` | `uniqueViolation()` | Runtime | A unique constraint was violated. |
| 6033 | `QueryException` | `foreignKeyViolation()` | Runtime | A foreign key constraint was violated. |
| 6034 | `QueryException` | `notNullViolation()` | Runtime | A NOT NULL constraint was violated. |
| 6035 | `QueryException` | `deadlock()` | Runtime | The transaction was chosen as a deadlock victim. |
| 6036 | `QueryException` | `lockTimeout()` | Runtime | Lock wait timeout exceeded. |
| 6037 | `QueryException` | `invalidParameterCount()` | Runtime | The bound parameters do not match the statement placeholders. |
| 6038 | `QueryException` | `prepareFailed()` | Runtime | The statement could not be prepared. |
| 6039 | `QueryException` | `executeFailed()` | Runtime | The statement failed to execute. |
| 6040 | `QueryException` | `invalidBuilderState()` | Runtime | The query builder is in an invalid state. |
| 6041 | `QueryException` | `missingColumn()` | Runtime | The result row lacks an expected column. |
| 6060 | `TransactionException` | `noActiveTransaction()` | Runtime | There is no active transaction. |
| 6061 | `TransactionException` | `savepointFailed()` | Runtime | A savepoint operation failed. |
| 6062 | `TransactionException` | `commitFailed()` | Runtime | The transaction could not be committed. |
| 6063 | `TransactionException` | `advisoryLockNotAcquired()` | Runtime | The advisory lock was not acquired; another session holds it. |
| 6080 | `PlatformException` | `unsupportedFeature()` | Configuration | This platform does not support the feature: {$feature}. |
| 6081 | `PlatformException` | `savepointsNotSupported()` | Configuration | This platform does not support savepoints. |
| 6082 | `PlatformException` | `schemasNotSupported()` | Configuration | This platform does not support schemas. |
| 6083 | `PlatformException` | `advisoryLocksNotSupported()` | Configuration | This platform does not support advisory locks. |
| 6084 | `PlatformException` | `unknownColumnType()` | Configuration | Unknown column type. |
| 6100 | `SchemaException` | `tableNotFound()` | NotFound | The requested table does not exist. |
| 6101 | `SchemaException` | `columnNotFound()` | NotFound | The requested column does not exist. |
| 6102 | `QueryException` | `introspectionFailed()` | Runtime | Schema introspection failed. |
| 6120 | `TypeConversionException` | `conversionFailed()` | Runtime | Cannot convert a {$phpType} value to the {$dbType} type. |
| 6121 | `ConfigurationException` | `unknownType()` | Configuration | No type converter is registered under the requested name. |

### Ecosystem registry (as recorded on 2026-10-04)

- **ORM 5200–5399** — reserved by this component's map and in use by kariricode/orm since 4.7.0.
- **Email ≥ 6200** — reserved. kariricode/nosql 4.1 (this repository's sibling) declares 6200–6399, which overlaps the reservation; the NoSQL range must move before either package is published.
- Open conflicts, to be resolved where the registry will live (the KC Cache proposal: kariricode/contract or the Specification): Runtime × I18n (both 9100–9199); CLI 9200–9399 × Tenancy 9200–9299; DataFlow start (5000 vs 5100).

