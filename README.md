# KaririCode\Database

[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-blue)](https://www.php.net/releases/8.4/)
[![ARFA 1.3](https://img.shields.io/badge/ARFA-1.3-green)](https://kariricode.org)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

Database Abstraction Layer (DBAL) for the KaririCode ecosystem. Provides driver-agnostic database connectivity, middleware pipeline decoration, platform dialect abstraction, extensible type system, and read-only schema introspection.

## Why

Three KaririCode packages (ORM, DataFlow, Tenancy) independently reimplemented PDO wrappers, connection management, and transaction handling. `kariricode/database` consolidates this into a single, well-tested DBAL foundation that all three depend on — eliminating duplication, enabling shared middleware (logging, profiling, tenancy), and providing semantic exception conversion across all database operations.

## Installation

```bash
composer require kariricode/database
```

Requires PHP 8.4+ and at least one database extension:

```bash
# MySQL/MariaDB (most common)
ext-pdo_mysql

# PostgreSQL
ext-pdo_pgsql

# SQLite
ext-pdo_sqlite
```

## Quick Start

```php
use KaririCode\Database\Connection\ConnectionRegistry;
use KaririCode\Database\ValueObject\ConnectionParams;

// Configure connections
$manager = new ConnectionRegistry([
    'default' => new ConnectionParams(
        driver: 'pdo_mysql',
        host: 'localhost',
        port: 3306,
        database: 'myapp',
        username: 'root',
        password: 'secret',
        charset: 'utf8mb4',
    ),
]);

// Get connection (lazy — connects on first use)
$conn = $manager->defaultConnection();

// Query
$result = $conn->executeQuery(
    'SELECT * FROM users WHERE status = ?',
    ['active'],
);

foreach ($result->fetchAllAssociative() as $row) {
    echo $row['name'] . "\n";
}

// Transactional write
$conn->transactional(function ($conn) {
    $conn->executeStatement(
        'INSERT INTO users (name, email) VALUES (?, ?)',
        ['Walmir', 'walmir@kariricode.org'],
    );
});
```

## Connection Management

Named connections with lazy initialization, from `ConnectionParams` or a DSN:

```php
$manager = new ConnectionRegistry([
    'primary' => new ConnectionParams(driver: 'pdo_pgsql', ...),
    'analytics' => 'mysql://app:secret@analytics:3306/events?charset=utf8mb4',
    'cache' => 'sqlite:////var/lib/app/cache.db',   // absolute; sqlite:///cache.db is relative
]);

$primary = $manager->get('primary');
$analytics = $manager->get('analytics');
```

With a PSR-14 dispatcher on the factory, each connect is reported as `ConnectionEstablished` (with the registry name) or `ConnectionError`.

## Read/Write Splitting

Transparent routing to read replicas:

```php
use KaririCode\Database\Connection\ReadWriteConnection;

$conn = new ReadWriteConnection(
    write: $manager->get('primary'),
    reads: [$manager->get('replica1'), $manager->get('replica2')],
    sticky: true,
);

// Routed to random replica
$conn->executeQuery('SELECT * FROM users');

// Routed to primary
$conn->executeStatement('UPDATE users SET ...');

// Sticky: all subsequent reads go to primary
$conn->executeQuery('SELECT * FROM users WHERE id = ?', [1]);
```

## Transactions

Savepoint-based nesting:

```php
$conn->beginTransaction();              // BEGIN
    $conn->executeStatement('INSERT ...');
    $conn->beginTransaction();          // SAVEPOINT kariricode_sp_2
        $conn->executeStatement('INSERT ...');
        $conn->rollBack();              // ROLLBACK TO SAVEPOINT kariricode_sp_2
    // Inner work undone, outer still active
$conn->commit();                        // COMMIT
```

## Middleware Pipeline

Decorate drivers with cross-cutting concerns:

```php
use KaririCode\Database\Middleware\LoggingMiddleware;
use KaririCode\Database\Middleware\RetryMiddleware;
use KaririCode\Database\Middleware\TenancyMiddleware;
use KaririCode\Database\Resilience\TransientFailureRetryPolicy;

// Outermost first (ADR-002): Logging, Profiling, Retry, Tenancy.
$factory = new ConnectionFactory([
    new LoggingMiddleware($psrLogger),
    new RetryMiddleware(new TransientFailureRetryPolicy(maxAttempts: 3)),
    new TenancyMiddleware(static fn(): ?string => $tenant->schema()),
]);
```

`RetryMiddleware` retries only the connect, never a statement, so a retry cannot repeat a write.

## Query Builder

Entity-agnostic, dialect-aware SQL query builder (ref: Doctrine DBAL 4.4 — QueryBuilder is in DBAL, not ORM):

```php
$qb = $conn->createQueryBuilder();
$result = $qb
    ->select('u.id', 'u.name', 'u.email')
    ->from('users', 'u')
    ->innerJoin('u', 'profiles', 'p', 'u.id = p.user_id')
    ->where('u.status = ?')
    ->orderBy('u.name', 'ASC')
    ->setMaxResults(20)
    ->setParameter(0, 'active')
    ->executeQuery();

// Memory-efficient iteration (generator-backed — O(1) memory)
foreach ($result->iterateAssociative() as $row) {
    process($row);
}
```

The ORM extends this builder with entity-aware features (hydration, relations). DataFlow uses it directly for ETL queries.

## Error Handling

Every exception implements `KaririCode\Database\Exception\DatabaseException` and exactly one `kariricode/contract` family, so an edge maps it without knowing this package:

| Family | Classes | HTTP |
|---|---|---|
| `Runtime` | `ConnectionException`, `TransactionException`, `QueryException`, `TypeConversionException` | 503 or 500 |
| `Configuration` | `ConfigurationException`, `PlatformException` | 500 |
| `NotFound` | `SchemaException` | 404 |

Messages carry a `[DB_nnnn]` code and never echo SQL, driver text, DSNs or values; the driver error stays on `getPrevious()`, and `QueryException::$sqlState` exposes the SQLSTATE. Full map: SPEC §12.

Driver errors are converted where they happen (ADR-004): a duplicate key is `QueryException` 6032 on every vendor, wrong credentials are `ConfigurationException` 6012, an unreachable server is `ConnectionException` 6010. No `PDOException` reaches the caller.

## PSR Integration

| Concern | Port | Where |
|---|---|---|
| Events | PSR-14 `EventDispatcherInterface` | `new ConnectionFactory($middlewares, $dispatcher)` |
| Result cache | PSR-16 `CacheInterface` | `new CacheProfile(ttl: 60, cache: $cache)` |
| Query log | PSR-3 `LoggerInterface` | `new LoggingMiddleware($logger)` — logs SQL and parameter count, never values |
| Retry | `Contract\Resilience\RetryPolicy` | `new RetryMiddleware(new TransientFailureRetryPolicy())` |

## Platform Support

| Database       | Driver          | Platform            |
|----------------|-----------------|---------------------|
| MySQL 5.7+     | `pdo_mysql`     | `MySqlPlatform`     |
| MariaDB 10.4+  | `pdo_mysql`     | `MySqlPlatform`     |
| PostgreSQL 12+ | `pdo_pgsql`     | `PostgreSqlPlatform`|
| SQLite 3.8+    | `pdo_sqlite`    | `SqlitePlatform`    |

CI runs the server suites (`tests/Integration/Server`) against PostgreSQL 16 and MariaDB 10.11, and the rest of the suite on SQLite; the older versions above are what the platforms detect, not what CI runs. To run the server suites locally, point the environment at your servers:

```bash
export KARIRICODE_DATABASE_PGSQL_URL='pgsql://user:pass@127.0.0.1:5432/test'
export KARIRICODE_DATABASE_MYSQL_URL='mysql://user:pass@127.0.0.1:3306/test'
composer test
```

Without them those tests are skipped.

## Ecosystem Position

`kariricode/database` is the DBAL foundation layer:

```
kariricode/contract  ←  kariricode/database
                            ↑        ↑        ↑
                      kariricode/  dataflow  tenancy
                          orm
```

It replaces duplicated PDO wrappers in ORM, DataFlow, and Tenancy with a single, tested implementation featuring middleware support, semantic exceptions, and platform-aware type conversion.

## Architecture

| Layer      | Files | Purpose                                   |
|------------|------:|-------------------------------------------|
| Contract   | 12    | Interfaces (promoted to kariricode/contract in Phase 2) |
| Driver     | 10    | PDO drivers, wrappers and exception converters |
| Connection | 4     | Transactions, savepoints, advisory locks, events; registry, factory, read/write routing |
| Cache      | 2     | Result cache profile and cached result    |
| Query      | 3     | Entity-agnostic SQL query builder         |
| Middleware | 4     | Decoration: logging, profiling, tenancy, retry |
| Platform   | 3     | SQL dialect abstraction                   |
| Type       | 20    | PHP↔DB type conversion registry           |
| Schema     | 3     | Read-only metadata introspection          |
| ValueObject| 8     | Immutable configuration and metadata objects |
| Enum       | 7     | Driver, parameter, column and isolation types |
| Event      | 6     | PSR-14 connection, query and transaction events |
| Exception  | 8     | Semantic exception hierarchy with factories |
| Resilience, DSN | 2 | Retry policy; DATABASE_URL parser     |
| **Total**  | **92** |                                          |

## Documentation

| Document | Description |
|----------|-------------|
| [SPEC-001](docs/spec/SPEC-001-database-abstraction-layer.md) | Full component specification |
| [ADR-001](docs/adr/ADR-001-two-layer-driver-connection.md) | Two-layer architecture |
| [ADR-002](docs/adr/ADR-002-middleware-decoration-pipeline.md) | Middleware pipeline |
| [ADR-003](docs/adr/ADR-003-platform-dialect-abstraction.md) | Platform dialects |
| [ADR-004](docs/adr/ADR-004-semantic-exception-conversion.md) | Exception conversion |
| [ADR-005](docs/adr/ADR-005-savepoint-based-nested-transactions.md) | Nested transactions |
| [ADR-006](docs/adr/ADR-006-read-write-connection-splitting.md) | Read/write splitting |
| [ADR-007](docs/adr/ADR-007-type-registry-extensible.md) | Type registry |
| [ADR-008](docs/adr/ADR-008-schema-inspector-read-only.md) | Schema inspector |
| [ADR-009](docs/adr/ADR-009-sql-query-builder-in-dbal.md) | SQL query builder in DBAL |
| [ADR-010](docs/adr/ADR-010-result-cache.md) | Result cache |
| [ADR-011](docs/adr/ADR-011-dsn-parser.md) | DSN parser |
| [ADR-012](docs/adr/ADR-012-bulk-insert.md) | Bulk insert |
| [ADR-013](docs/adr/ADR-013-connection-events.md) | Connection events |
| [ADR-014](docs/adr/ADR-014-spec-naming.md) | Spec naming |
| [ADR-015](docs/adr/ADR-015-spec-v433-migration.md) | Spec V4.3.3 migration |
| [ADR-016](docs/adr/ADR-016-toolchain-through-kcode.md) | Gates through KaririCode Devkit; mutation floor |
| [Competitive Analysis](docs/COMPETITIVE-ANALYSIS.md) | vs Doctrine DBAL, Laravel DB |
| [Critical Review](docs/CRITICAL-REVIEW.md) | 14 issues found and resolved |

## License

MIT © 2025-2026 Walmir Silva
