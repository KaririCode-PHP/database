# Changelog

## [Unreleased]

### Tooling

- Imported into KaririCode-PHP/database. The toolchain comes from KaririCode Devkit, pinned in `devkit.php`, and runs the root configs (ADR-016): `require-dev` and the unused PHPMD are gone, `composer.lock` holds the runtime only (kariricode/contract 2.12.0, psr/event-dispatcher, psr/log, psr/simple-cache), and `composer security` also audits the toolchain. CI installs kcode 1.2.1 by version and SHA-256.
- First mutation run: MSI 60%. `composer infection` holds that floor; the Spec asks for 80% (QD-V433).

## [4.4.0] — 2026-10-04

Static analysis closes: PHPStan level max and Psalm errorLevel 1 report 0 (were 329 and 172). Breaking behaviour ships as a minor version (no installable release yet).

### Changed — breaking (behaviour)
- Type converters go through `Type\ScalarValue`: a value that cannot be represented throws `TypeConversionException` (6120) instead of being coerced. `(int) 'abc'` used to be 0 and `'12.5'` read as an integer was truncated.
- Catalog rows are read through `ValueObject\ResultRow`; a missing column throws `QueryException::missingColumn()` (6041) instead of reading as `''` or `0`. A NULL read through a non-nullable accessor still becomes `''`, `0` or `false`. Every column the MySQL and PostgreSQL inspectors read was checked against their SELECT lists.

### Security
- `SqliteSchemaInspector` bound the table name to `pragma_*()` table-valued functions instead of interpolating it into `PRAGMA table_info(...)`; an injection attempt is now just an unknown table (tested).

### Added
- Array value types declared on every contract and implementation; `ScalarValue` and `ResultRow`.
- Tests: strict scalar conversion (accept/reject tables, no echoed value), `ResultRow`, type converters, SQLite schema inspector. 71 tests, 1620 assertions.

## [4.3.0] — 2026-10-04

Migration to Spec V4.3.3 and kariricode/contract 2.11 (ADR-015). Breaking changes ship as a minor version (no installable release yet).

### Changed — breaking
- `DatabaseException` is a marker interface; every exception implements one contract family, has a private constructor and a `[DB_nnnn]` message that echoes no SQL, driver text, DSN or value. New `ConfigurationException` takes `authenticationFailed`, `databaseNotFound`, `invalidDsn`, `unknownDsnScheme`, `unknownType` (codes kept) and the new `connectionNotConfigured` (6018) and `invalidCharset` (6019). `introspectionFailed` (6102) moved to `QueryException`. Factories no longer take a reason string.
- PSR ports: `ConnectionFactory`/`DefaultConnection` take `Psr\EventDispatcher\EventDispatcherInterface`; `CacheProfile`/`DefaultConnection` take `Psr\SimpleCache\CacheInterface`; `LoggingMiddleware` takes `Psr\Log\LoggerInterface`.
- `RetryMiddleware` takes `Contract\Resilience\RetryPolicy` (default `Resilience\TransientFailureRetryPolicy`: 3 attempts in total, full jitter).
- `Platform::getReleaseAdvisoryLockSql()` joins the contract.
- Removed: `Bridge\LoggingBridge`, `Bridge\EventDispatcherBridge`, `Bridge\ObservabilityBridge`.
- `composer.json`: `extra.kariricode` replaces `extra.arfa`; ARFA level 1.

### Fixed
- MySQL error 1049 passed the SQL as the database name.
- Unknown driver errors were reported as syntax errors; now `QueryException::executeFailed()` (6039).
- MySQL unique, foreign-key and not-null violations were reported as a generic constraint violation (SQLSTATE 23000 was consulted first).
- An advisory lock that was not acquired raised "feature not supported"; now `TransactionException::advisoryLockNotAcquired()` (6063).
- `LoggingMiddleware` logged parameter values; it now logs the count.
- The password parameter of `ConnectionParams` carries `#[\SensitiveParameter]`.

### Added
- Test suite inside the package: 45 tests (integration on SQLite, unit, conformance).
- Gate tooling, `tools/complexity.php`, `tools/secret-scan.php`, `tools/sbom.php`, CI pinned by commit SHA, LICENSE, CONTRIBUTING, SECURITY.

## [4.2.0] — 2026-10-04

Names and accessors follow Spec V4.3.3 §16 and §18 (ADR-014). Breaking changes ship as a minor version (no installable release yet). Rename map in ADR-014.

### Changed — breaking
- `ConnectionManager` → `ConnectionRegistry`; `getDefault()`/`getNames()` → `defaultConnection()`/`names()`.
- `Connection::getPlatform()` → `$platform` and `Platform::getVersion()` → `$version`, as `{ get; }` properties.
- The remaining zero-argument getters become noun-named query methods: `sql()`, `parameters()`, `parameterTypes()`, `nativeConnection()`, `transactionNestingLevel()`, `transactionIsolation()`, `currentTimestampSql()`, `maxPlaceholders()`, `driverType()`, `profiles()`.

### Security
- `DsnParser` echoed the whole DSN, including the password, in `InvalidArgumentException` messages. It now throws `ConnectionException::invalidDsn()` (6016) and `ConnectionException::unknownDsnScheme()` (6017), which name no input (Spec V4.3.3 E-047; CWE-532).

## [4.0.0] — 2026-03-08

### Added

- Two-layer Driver/Connection architecture (ADR-001)
- Middleware decoration pipeline with required ordering (ADR-002)
- Platform dialect abstraction — MySQL, PostgreSQL, SQLite (ADR-003)
- Savepoint-based nested transactions (ADR-005)
- Read/write connection splitting with sticky mode (ADR-006)
- SQL query builder — entity-agnostic, dialect-aware (ADR-009)
- Result cache with PSR-6 integration (ADR-010, GAP-DB-01)
- DSN parser for URL-based configuration (ADR-011, GAP-DB-03)
- Bulk insert with platform-aware auto-chunking (ADR-012, GAP-DB-05)
- Connection lifecycle events (ADR-013, GAP-DB-07)
- Semantic exception conversion — 25 factories, range DB-6000–6199 (ADR-004)
- Advisory lock convenience API (ISSUE-009)
- Transaction isolation level control (GAP-DB-04)
- Generator-backed lazy iteration on Result (ISSUE-002)
- Schema inspector with optional schema qualification (ISSUE-008)
- Type system with 13 built-in converters + custom registration (ADR-007)
- Health check / ping on Connection and ConnectionManager (GAP-CROSS-01)
- PHP 8.4 PDO subclasses (Pdo\Mysql, Pdo\Pgsql) (GAP-DB-08)
- Version-aware platforms with PlatformVersion VO (GAP-DB-02)
- DataStoreEvent marker interface shared with kariricode/nosql (GAP-CROSS-02)
