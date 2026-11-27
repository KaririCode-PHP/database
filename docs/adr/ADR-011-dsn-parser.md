# ADR-011 — DSN Parser for URL-Based Configuration

| Field  | Value      |
|--------|------------|
| Status | Accepted   |
| Date   | 2026-03-08 |

## Context

12-factor apps configure databases via `DATABASE_URL` environment variable (`mysql://user:pass@host:3306/dbname`). Both Doctrine (`DsnParser`) and Laravel parse this format. Without it, KaririCode requires explicit `ConnectionParams` — friction for production deployments.

## Decision

`DsnParser::parse(string $dsn): ConnectionParams` following RFC 3986 URI syntax. Supports `mysql://`, `pgsql://`, `sqlite://`, `mariadb://` schemes with custom scheme-to-driver mapping.

## Consequences

**Positive:** Zero-friction production configuration. Compatible with Heroku, AWS RDS, Railway, etc.
**Negative:** Password in URL string — same security consideration as every other framework.

## References

- Doctrine DBAL 4.4 `DsnParser`: custom scheme mapping, RFC 3986 parsing
- Laravel `DATABASE_URL` support since 5.x
