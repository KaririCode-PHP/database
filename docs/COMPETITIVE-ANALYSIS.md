# Competitive Analysis — KaririCode\Database vs Market

| Date | 2026-03-08 |
|------|------------|

## Competitors Analyzed

| Library            | Version | LOC    | PHP Req | Downloads/month |
|--------------------|---------|--------|---------|-----------------|
| Doctrine DBAL      | 4.4     | ~45,000 | 8.1+   | ~35M            |
| Laravel Illuminate\Database | 12.x | ~25,000 | 8.2+ | ~30M (via framework) |
| CakePHP Database   | 5.x     | ~18,000 | 8.1+   | ~500K           |
| Cycle DBAL         | 2.x     | ~12,000 | 8.1+   | ~200K           |

---

## Feature Comparison Matrix

| Feature                              | Doctrine 4 | Laravel 12 | KaririCode (Design) |
|--------------------------------------|:----------:|:----------:|:-------------------:|
| **Architecture**                     |            |            |                     |
| Two-layer Driver/Wrapper             | ✅         | ❌ (single class) | ✅            |
| Middleware pipeline                  | ✅         | ❌ (events only)  | ✅            |
| Platform abstraction                 | ✅ (abstract class) | ✅ (grammars) | ✅ (final classes) |
| Zero-inheritance platforms           | ❌ (AbstractPlatform 2K LOC) | ❌ | ✅        |
| **Connection Management**            |            |            |                     |
| Named connections                    | ❌ (external) | ✅     | ✅                  |
| Read/write splitting                 | ❌ (PrimaryReadReplica) | ✅ | ✅           |
| Sticky mode                          | ❌         | ✅         | ✅                  |
| Lazy connection                      | ✅         | ✅         | ✅                  |
| **Transactions**                     |            |            |                     |
| Savepoint nesting                    | ✅         | ✅ (silent) | ✅ (explicit)      |
| `transactional()` helper             | ✅         | ✅ (`DB::transaction()`) | ✅        |
| Explicit failure on unsupported      | ✅         | ❌ (silent no-op) | ✅           |
| **Type System**                      |            |            |                     |
| Built-in type converters             | ✅ (30+)   | ❌ (casts in Eloquent) | ✅ (14)   |
| Custom type registration             | ✅         | ❌ (model-level) | ✅            |
| Platform-aware SQL generation        | ✅         | ❌         | ✅                  |
| **Schema**                           |            |            |                     |
| Schema introspection                 | ✅ (full SchemaManager) | ✅ (Schema facade) | ✅ (read-only) |
| Schema modification                  | ✅         | ✅         | ❌ (by design — ORM) |
| **Exception Handling**               |            |            |                     |
| Semantic exception conversion        | ✅         | ❌ (raw QueryException) | ✅        |
| SQLSTATE + error code mapping        | ✅         | ❌         | ✅                  |
| Error code ranges (ecosystem-safe)   | ❌         | ❌         | ✅ (DB-6000–6199)   |
| **Modern PHP**                       |            |            |                     |
| PHP 8.4+ required                    | ❌ (8.1+)  | ❌ (8.2+)  | ✅                  |
| `readonly` value objects             | Partial    | ❌         | ✅ (all VOs)        |
| `final` on all concrete classes      | Partial    | ❌         | ✅                  |
| Pure enums                           | ❌         | ❌         | ✅ (2 pure, 4 backed justified) |
| Property hooks                       | ❌         | ❌         | Where appropriate    |
| **Ecosystem Integration**            |            |            |                     |
| Zero hard dependencies               | ❌ (psr/log, psr/cache) | ❌ (illuminate/*) | ✅ (only kariricode/contract) |
| Duck-typed bridges                   | ❌         | ❌         | ✅                  |
| Observability hooks                  | Via middleware | Via events | Via middleware + bridge |

---

## Key Differentiators

### vs Doctrine DBAL 4

Doctrine is the gold standard for PHP DBAL. KaririCode\Database takes direct inspiration from its two-layer architecture and middleware pipeline but improves on:

1. **No abstract base class** — Doctrine's `AbstractPlatform` is 2,000+ LOC with 50+ overridable methods. KaririCode uses `final readonly` platform classes (~200 LOC each), avoiding the fragile base class antipattern.

2. **Ecosystem-native** — Doctrine is standalone. KaririCode\Database integrates via the `kariricode/contract` ecosystem: shared error code ranges, bridge pattern for logging/observability/events, consistent ARFA 1.3 conventions.

3. **PHP 8.4+ only** — Doctrine supports PHP 8.1+. KaririCode can leverage `readonly` classes, property hooks, and native lazy objects without backwards-compatibility shims.

4. **Smaller scope** — Doctrine bundles schema modification, query builder, and events. KaririCode\Database is the thin DBAL layer — query building is ORM, schema modification is ORM, events are ecosystem bridges. This keeps the package at ~65 files vs Doctrine's ~300.

### vs Laravel Illuminate\Database

Laravel's database layer is tightly coupled to the framework (Service Container, Facades, Eloquent). KaririCode\Database:

1. **Framework-agnostic** — No framework dependency. Can be used in any PHP 8.4+ application.
2. **Explicit transaction nesting** — Laravel silently ignores inner rollback. KaririCode uses savepoints.
3. **Semantic exceptions** — Laravel wraps everything in a generic `QueryException`. KaririCode converts to typed exceptions (`UniqueViolation`, `ForeignKeyViolation`, etc.).
4. **Type system at DBAL level** — Laravel handles type casting at the Eloquent model level. KaririCode provides platform-aware type conversion at the connection level, benefiting all consumers (ORM, DataFlow, raw queries).

---

## Positioning

KaririCode\Database occupies a specific niche:

```
Doctrine DBAL    ← Full-featured, standalone, enterprise
KaririCode DB    ← Focused DBAL, ecosystem-native, PHP 8.4+, ARFA-compliant
Laravel DB       ← Framework-coupled, opinionated, Eloquent-centric
```

It is not trying to replace Doctrine DBAL for standalone use. It is the **foundation layer** of the KaririCode ecosystem, purpose-built to serve ORM, DataFlow, and Tenancy with minimal API surface and maximal PHP 8.4+ idiom adoption.
