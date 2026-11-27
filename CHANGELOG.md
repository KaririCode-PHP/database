# Changelog

## [Unreleased]

## [4.4.0] — 2026-11-27

First published version. The code is the 4.4.0 reviewed on 2026-10-04, never tagged then. Running it against PostgreSQL 16 and MariaDB 10.11 for the first time, and raising the mutation score, found the bugs fixed below; the toolchain, the server suites and the documentation were added when it was imported.

### Fixed

- Driver errors reached callers as raw `PDOException`: the exception converters of ADR-004 existed, but no driver called them. `PdoConnection`, `PdoStatement` and `PdoResult` now convert every error of prepare, exec, execute, fetch, the transaction calls and `lastInsertId()`, so a duplicate key is `QueryException` 6032 on every vendor (ADR-004, Amendment).
- A refused connection was always `ConnectionException` 6010. Wrong credentials are now `ConfigurationException` 6012 and an unknown database 6013 (MySQL 1044, 1045, 1049; PostgreSQL by the server message, since libpq reports 08006 for all), so `RetryMiddleware` no longer retries them.
- PostgreSQL connections failed with the default charset: `SET NAMES 'utf8mb4'` is not a PostgreSQL encoding. utf8mb4 and utf8 are sent as UTF8.
- `ping()` ran `SELECT 1` through `exec()`, which leaves the result pending on MySQL: the next query on the connection failed with error 2014.
- On MariaDB `advisoryLock()` always failed: `GET_LOCK(name, -1)` returns NULL there (MySQL reads -1 as no timeout). The wait is now one year, on both servers.
- `setTransactionIsolation()` on MySQL applied to the next transaction only, while `transactionIsolation()` kept reporting the level; it now sets the session level, as on PostgreSQL.
- `fetchAll()` of pdo_sqlite returns the rows read before an error without throwing; `fetchAllAssociative()` and the other eager reads handed back a partial result as if complete. The error is now converted.
- A bulk insert took its columns from the first row only, so a column present only in later rows was dropped without notice. The columns are the union of every row's, missing values are NULL.
- An upsert whose columns were all conflict columns produced invalid SQL on every platform; it now keeps the existing row (`DO NOTHING`, or `col = col` on MySQL).
- `DsnParser` could not read SQLite DSNs with slashes (`parse_url()` rejects `sqlite:///path`), and stripped every leading slash of a path. `sqlite:////var/app.db` is absolute, `sqlite:///app.db` relative, `sqlite:///:memory:` in memory; a server DSN with an empty host (`mysql:///shop`) means localhost.
- `DateTimeTzType` wrote the offset into a MySQL or MariaDB DATETIME, which MariaDB rejects; the instant is written and read in UTC there. `DateIntervalType` lost the sign of a negative interval, and on PostgreSQL could not read back the INTERVAL it declared; it stores the ISO 8601 duration in VARCHAR(255) everywhere.
- Column defaults read differently per server: MariaDB quotes literals and reports a missing default as the text NULL, PostgreSQL appends a cast (`'open'::character varying`), SQLite returns the DDL text. All three inspectors return the literal bare and no default as null.
- The PostgreSQL inspector looked in `public` when no schema was given, so after `TenancyMiddleware` it read the wrong schema; it now uses `current_schema()`. It also listed a composite foreign key once per column pair (x, x, y, y against a, b, a, b): `constraint_column_usage` has no position, and the columns are now paired through `position_in_unique_constraint`.
- `ConnectionEstablished` was never dispatched; `ConnectionFactory` now dispatches it, with the registry name, and `ConnectionError` when a connect fails (ADR-013, Amendment).
- `driverOptions` were merged into the defaults with the spread operator in `PdoSqliteDriver`, `PdoMySqlDriver` and `PdoPgSqlDriver`. The spread renumbers integer keys, so every user option landed on another PDO attribute and the default it meant to replace stayed: on SQLite a fetch mode given by the user became `ATTR_EMULATE_PREPARES`, on MySQL and PostgreSQL `ATTR_DEFAULT_STR_PARAM`. The arrays are now merged with `array_replace()`, which keeps the keys.
- `SqlQueryBuilder::values()` wrote the `?` placeholders of the INSERT but never bound the values, so SQLite stored NULL in every column. The values are now the positional parameters of those placeholders.
- An unknown driver name failed with a `ValueError` from the enum; it is `ConfigurationException::unknownDriver()` (6020).
- The MySQL charset went into the DSN unchecked; like PostgreSQL, it may hold only letters, digits, `-` and `_` (6019).

### Changed

- `ConnectionFactory::createConnection()` takes the connection name as an optional second argument (`'default'`); `ConnectionRegistry` passes its own.
- `PdoConnection`, `PdoStatement` and `PdoResult` take the driver's `ExceptionConverter`; `AbstractExceptionConverter::convertConnectFailure()` converts connect errors.
- SQLite busy and locked errors (result codes 5 and 6) are lock timeouts (6036), which the retry policy treats as transient.

### Added

- Server suites in `tests/Integration/Server` for PostgreSQL and MySQL or MariaDB, run when `KARIRICODE_DATABASE_PGSQL_URL` or `KARIRICODE_DATABASE_MYSQL_URL` holds a DSN, and in CI against PostgreSQL 16 and MariaDB 10.11 service containers pinned by digest: refused connections, vendor errors, savepoints, session isolation, advisory locks between two sessions, upserts, the catalog readers, tenancy, and every built-in type in a server column. The type round trip also runs on SQLite.
- Tests that pin the vendor-code and SQLSTATE tables, retry classification and backoff, version comparison, scalar conversion, value-object defaults, SQLite quoting, version thresholds and schema inspection, connection logging, events, cache, transactions, savepoints, read/write routing, the exact SQL of the query builder, and every conversion path of the PDO wrappers.
- Docblocks with the five criteria of §17 on all 92 types, enforced by `SpecRulesTest` (QD-V433).

### Toolchain

- Imported into KaririCode-PHP/database. The toolchain comes from KaririCode Devkit, pinned in `devkit.php`, and runs the root configs (ADR-016): `require-dev` and the unused PHPMD are gone, `composer.lock` holds the runtime only (kariricode/contract 2.12.0, psr/event-dispatcher, psr/log, psr/simple-cache), and `composer security` also audits the toolchain. CI installs kcode 1.2.1 by version and SHA-256.
- CI starts PostgreSQL 16 and MariaDB 10.11 for the server suites.
- Mutation: MSI 87% (1409 mutants with the server suites: 1210 killed, 15 errors, 2 time-outs, 182 escaped), up from 60% on the first run. `composer infection` applies the 80% of §19.4 set in `infection.json5`; the floor of 60 is gone (ADR-016, Amendment).

### Reviewed on 2026-10-04

Static analysis closes: PHPStan level max and Psalm errorLevel 1 report 0 (were 329 and 172). Breaking behaviour ships as a minor version.

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
