# QD-V433: kariricode/database 4.4.0 — compliance with Spec V4.3.3

**Status:** Measured · **Date:** 2026-10-04 · PHP 8.4.23 (static build), PHPUnit 11.5, PHPStan 2.2, Psalm 6.19, PHP-CS-Fixer 3.95

Reproduce with `composer check`; each number below comes from that run.

## Gates (§29.1)

| Gate | Command | Result |
|---|---|---|
| Static analysis | `composer phpstan` (level max) | pass: 0 errors on `src`, `tests` and `tools` |
| Static analysis | `composer psalm` (errorLevel 1) | pass: 0 errors |
| Style | `composer cs-check` (`@PER-CS3.0`) | pass: 0 files |
| Complexity | `composer complexity` | pass: 0 violations; 5 justified parameter exceptions in `tools/complexity-baseline.json` |
| Tests | `composer test` | pass: 71 tests, 1620 assertions (SQLite) |
| Conformance | `composer test-conformance` | pass: families, private constructors, codes, prefixes, interpolation allow-list, naming, state, markers |
| Mutation | `composer infection` | **below target**: MSI 60% (670 mutants, 405 killed, 265 escaped) measured 2026-11-27 with pcov; the gate holds this floor until the suite reaches 80 (ADR-016) |
| Security | `composer security` | pass: manifest valid, 0 advisories, 0 secrets |

## Rules

| Rule | Result |
|---|---|
| Exception families (§8.4) | 7 of 7 classes, one family each |
| Codes (registry 6000–6199) | 36 codes, unique, prefixed `[DB_nnnn]` |
| No echo of input (E-047) | messages are literals; interpolation limited to package-chosen names |
| `throw new` outside factories | 0 |
| Static mutable state (§12) | 0 static properties |
| `*Manager`/`*Helper`/`*Util` (§16), zero-argument `getX()` (§18) | 0 / 0 (ADR-014) |
| Duck typing (§31) | 0 `method_exists()` |
| Docblocks C1–C5 (§17) | **open**: 11 of 92 types |
| `find()`/`get()` (§9.3) | `ConnectionRegistry::get()` and `TypeRegistry` lookups fail with `Configuration`, since a missing name is a wiring error, not an absent record |
| Supply chain (§32) | CI pinned by SHA, kcode pinned by version and SHA-256, SBOM generator, audit of the runtime and the toolchain, secret scan; `composer.lock` committed against kariricode/contract 2.12.0 |
| Real databases | **pending**: SQLite only here; PostgreSQL and MySQL suites need servers |
