# ADR-007 — Extensible Type Registry

| Field  | Value      |
|--------|------------|
| Status | Accepted   |
| Date   | 2026-03-08 |

## Context

Database columns store data in vendor-specific representations (MySQL `TINYINT(1)` for booleans, PostgreSQL `JSONB` for JSON). PHP applications need automatic conversion between database and PHP representations. The set of types is not fixed — applications frequently need custom types (Money, PostalCode, Enum mappings).

## Decision

`TypeRegistry` is a `final` class holding a `array<string, TypeConverter>` map. It ships with 14 built-in converters covering all common SQL types (see SPEC-001 §8.1). Custom types are registered via `$registry->register(string $name, TypeConverter $converter)`.

Each `TypeConverter` implements three methods:
- `convertToDatabaseValue(mixed $value, Platform $platform): mixed`
- `convertToPhpValue(mixed $value, Platform $platform): mixed`
- `getSqlDeclaration(array $column, Platform $platform): string`

The Platform parameter allows type converters to emit dialect-specific SQL (e.g., `JSONB` on PostgreSQL, `JSON` on MySQL).

Type converters are `final readonly` classes — pure functions with no state.

## Alternatives Considered

1. **Annotation-based type mapping** — Declare types via PHP attributes on entity properties. Rejected: that's ORM-level mapping, not DBAL-level type conversion.
2. **Global singleton registry** — One static registry for the process. Rejected: prevents per-connection type customization, not thread-safe.
3. **No type system** — Return raw PDO values. Rejected: forces type conversion code throughout consumer packages.

## Consequences

**Positive:**
- ORM can register domain-specific types (Money, Email) at the DBAL level
- Platform-aware SQL generation for schema operations
- Stateless converters are trivially testable

**Negative:**
- Custom types must handle all 3 platforms (or throw `PlatformException::unsupportedFeature()`)
