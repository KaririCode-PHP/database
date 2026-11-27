# ADR-015: Migration to Spec V4.3.3 and kariricode/contract 2.11

**Status:** Accepted
**Date:** 2026-10-04
**Authors:** Walmir Silva
**Supersedes:** the `extra.arfa` declaration and the duck-typed bridges of 4.1

## Context

The KaririCode Specification moved to V4.3.3 and `kariricode/contract` 2.11.0 published the exception families (§8.4), the resilience ports (2.10.0) and the cross-cutting contracts. Measured against them, 4.2.0 had: exceptions without a family and with depth 3; messages that echoed SQL, driver text, DSNs and stored values; four `throw new` outside factories; five `method_exists()` checks (§31); a PSR-6 cache and a `\Closure` dispatcher instead of the PSR ports the contract mandates; its own retry loop; `extra.arfa` with level 2 although PDO is blocking.

## Decision

1. **Exceptions (§8.4, E-047).** `DatabaseException` is a marker interface extending `Contract\Exception\ExceptionInterface`. Each concrete exception is `final`, extends `\RuntimeException` (depth 2), has a private constructor (named factories only) and implements one family:

| Class | Family | Why |
|---|---|---|
| `ConnectionException` | Runtime | server unreachable or connection dropped; retryable |
| `ConfigurationException` (new) | Configuration | credentials, unknown database, DSN, unconfigured name, charset, unknown type |
| `PlatformException` | Configuration | the platform lacks a feature |
| `TransactionException` | Runtime | no transaction, savepoint, commit, advisory lock not acquired |
| `QueryException` | Runtime | syntax, constraints, deadlock, lock timeout; exposes `$sqlState` |
| `SchemaException` | NotFound | the requested table or column does not exist |
| `TypeConversionException` | Runtime | a value cannot be converted |

   Messages carry `[DB_nnnn]` and interpolate only names chosen by this package (`$feature`, `$phpType`, `$dbType`); the driver error stays on `getPrevious()`. Retired behaviour: the MySQL converter passed the SQL as the database name for error 1049, and the fallback labelled every unknown error a syntax error (now `executeFailed`, 6039). The converters consult the vendor code before the SQLSTATE, because MySQL reports every integrity violation as 23000.
2. **PSR ports (§31; contract policy "PSR is not mirrored").** Events: `Psr\EventDispatcher\EventDispatcherInterface`. Result cache: `Psr\SimpleCache\CacheInterface`, with a sentinel default so a cached empty result is a hit. Logging: `Psr\Log\LoggerInterface`; `LoggingMiddleware` logs the SQL template and the parameter count, never parameter values. The three duck-typed bridges are removed; observability subscribes to `QueryExecuted` through PSR-14.
3. **Retry (contract 2.10.0).** `RetryMiddleware` takes `Contract\Resilience\RetryPolicy`; the package ships `Resilience\TransientFailureRetryPolicy` (connection failures, deadlock 6035, lock timeout 6036; full-jitter backoff). Only connection establishment is retried, so no transaction is ever replayed.
4. **Advisory locks.** `Platform::getReleaseAdvisoryLockSql(int $key): ?string` replaces a `method_exists()` probe; null means the lock is transaction-scoped (PostgreSQL). A lock that is not acquired throws `TransactionException::advisoryLockNotAcquired()`.
5. **Metadata (§4, E-044).** `extra.kariricode` with `spec 4.3.3`, ARFA 1.3 **level 1** (PDO is blocking; level 2 needs non-blocking I/O), ASVS 5.0 level 1 and range 6000–6199.
6. **Tooling (§29.1, §32).** PHPStan max, Psalm level 1, PHP-CS-Fixer `@PER-CS3.0`, Infection (MSI ≥ 80), an AST complexity checker (`tools/complexity.php`, nikic/php-parser, because PHPMD 2.15 cannot parse PHP 8.4 interface properties or `private(set)`) with a justified baseline, a secret scanner, a CycloneDX 1.5 SBOM generator, and CI with actions pinned by commit SHA.

## Alternatives Considered

- **A `ConstraintViolationException` in the `Validation` family** (HTTP 422). Deferred: the ORM 4.7 maps its query failures to `Runtime`; changing both together keeps the edge consistent.
- **PHPMD as the complexity gate.** Rejected: it fails to parse PHP 8.4 syntax used here.
- **Keep the bridges, typed against PSR.** Rejected: they duplicated what the PSR ports already do.

## Consequences

- Breaking (4.3.0): exception classes and factories, constructor types of `ConnectionFactory`, `DefaultConnection`, `CacheProfile` and `LoggingMiddleware`, `RetryMiddleware`'s constructor, removed bridges.
- `require` gains `kariricode/contract ^2.11` and three PSR interface packages. Until 2.11.0 is tagged, installation needs a path repository and `composer.lock` is not committed (§32 pending).
- Open gates are measured in `docs/quality/QD-V433-COMPLIANCE.md`.

## References

- KaririCode Spec V4.3.3 §4, §8.4, §12, §13, §17, §29.1, §31, §32; errata E-044, E-047.
- kariricode/contract 2.10.0 (resilience ports) and 2.11.0 (exception families, migration table §8.5).
- PSR-3, PSR-14, PSR-16 (PHP-FIG).
- Brooker, M. (2015). "Exponential Backoff And Jitter". AWS Architecture Blog.
- CycloneDX 1.5 specification; CVE-2025-30066 (mutable action tags).
