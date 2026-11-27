# ADR-014: Names and accessors follow Spec V4.3.3 §16 and §18

**Status:** Accepted
**Date:** 2026-10-04
**Authors:** Walmir Silva

## Context

The API mirrored Doctrine DBAL: `ConnectionManager` and 14 zero-argument `getX()` methods (36 implementations). Spec V4.3.3 forbids both (§16, §18). On 2026-10-04 the maintainer chose to follow the Specification over the Doctrine convention.

## Decision

1. A value fixed at construction is a `public readonly` property, declared `{ get; }` on the interface; a computed or changing value is a query method named after the noun.
2. Renames:

| Before | After | Kind |
|---|---|---|
| `ConnectionManager` | `ConnectionRegistry` | class |
| `ConnectionRegistry::getDefault()` / `getNames()` | `defaultConnection()` / `names()` | methods |
| `Connection::getPlatform()` | `Connection::$platform` | property `{ get; }` |
| `Platform::getVersion()` | `Platform::$version` | property `{ get; }` |
| `Platform::getCurrentTimestampSql()` / `getMaxPlaceholders()` | `currentTimestampSql()` / `maxPlaceholders()` | methods (computed; SQLite depends on the version) |
| `Connection` and `DriverConnection::getNativeConnection()` | `nativeConnection()` | method |
| `Connection::getTransactionNestingLevel()` / `getTransactionIsolation()` | `transactionNestingLevel()` / `transactionIsolation()` | methods (change over time) |
| `QueryBuilder::getSQL()` / `getParameters()` / `getParameterTypes()` | `sql()` / `parameters()` / `parameterTypes()` | methods (computed) |
| `ConnectionParams::getDriverType()` | `driverType()` | method (computed in a readonly value object) |
| `ProfilingMiddleware::getProfiles()` | `profiles()` | method (changes over time) |

Methods with arguments named `get…` (`getLimitSql()`, `getUpsertSql()`, …) are not accessors and keep their names.

## Alternatives Considered

- **Keep the Doctrine names as a registered exception.** Rejected by the maintainer.
- **Properties for everything.** Rejected: changing state would need hooks in classes, and hooks on readonly state are forbidden (E-040).

## Consequences

- Breaking for every consumer of these members. The other packages in this chat (DataFlow 4.2, Tenancy 4.1, NoSQL 4.1) are outside the current scope; Tenancy's bridge checks `getPlatform` with `method_exists()` and falls back to its raw SQL path.
- `ReadWriteConnection` takes the primary's platform at construction.

## References

- Spec V4.3.3 §16 (no `*Manager`, `*Helper`, `*Util`) and §18 (no zero-argument `getX()`/`setX()`; use properties or interface properties `{ get; }`); E-040 (no hooks on readonly state).
- PHP 8.4 RFC "Property Hooks": interface properties; a `readonly` property satisfies `{ get; }`.
- Doctrine Persistence 4.0.0, `src/Persistence/ConnectionRegistry.php`: "Contract covering connection for a Doctrine persistence layer ManagerRegistry class to implement."
