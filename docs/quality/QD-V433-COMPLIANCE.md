# QD-V433: kariricode/database 4.4.0 — compliance with Spec V4.3.3

**Status:** Measured · **Date:** 2026-11-27 · PHP 8.4.28, PHPUnit 11.5.56, PHPStan 2.3.7, Psalm 6.19.1, PHP-CS-Fixer 3.95.27, Infection 0.35.5 (all pinned in `devkit.php`) · PostgreSQL 16.15 and MariaDB 10.11.14

Reproduce with `composer check`, with `KARIRICODE_DATABASE_PGSQL_URL` and `KARIRICODE_DATABASE_MYSQL_URL` pointing at the servers (CI starts both as service containers); each number below comes from that run.

## Gates (§29.1)

| Gate | Command | Result |
|---|---|---|
| Static analysis | `composer phpstan` (level max) | pass: 0 errors on `src`, `tests` and `tools` |
| Static analysis | `composer psalm` (errorLevel 1) | pass: 0 errors |
| Style | `composer cs-check` (`@PER-CS3.0`) | pass: 0 files |
| Complexity | `composer complexity` | pass: 0 violations; 5 justified parameter exceptions in `tools/complexity-baseline.json` |
| Tests | `composer test` | pass: 330 tests, 2423 assertions with both servers; without them the 71 server tests are skipped. 4.4.0 as reviewed on 2026-10-04 had 71 tests, all on SQLite |
| Conformance | `composer test-conformance` | pass: families, private constructors, codes, prefixes, interpolation allow-list, naming, state, markers, docblock criteria C1–C5 |
| Mutation | `composer infection` (MSI and covered MSI ≥ 80) | pass: MSI 87%, covered MSI 87% (1409 mutants: 1210 killed, 15 errors, 2 time-outs, 182 escaped) with both servers; the first run measured 60% (ADR-016, Amendment) |
| Security | `composer security` | pass: manifest valid, 0 advisories, 0 secrets |

## Rules

| Rule | Result |
|---|---|
| Exception families (§8.4) | 7 of 7 classes, one family each |
| Codes (registry 6000–6199) | 37 codes, unique, prefixed `[DB_nnnn]` |
| No echo of input (E-047) | messages are literals; interpolation limited to package-chosen names |
| `throw new` outside factories | 0 |
| Static mutable state (§12) | 0 static properties |
| `*Manager`/`*Helper`/`*Util` (§16), zero-argument `getX()` (§18) | 0 / 0 (ADR-014) |
| Duck typing (§31) | 0 `method_exists()` |
| Docblocks C1–C5 (§17) | pass: 92 of 92 types, enforced by `SpecRulesTest::testDocblocksMeetTheFiveCriteria` |
| `find()`/`get()` (§9.3) | `ConnectionRegistry::get()` and `TypeRegistry` lookups fail with `Configuration`, since a missing name is a wiring error, not an absent record |
| Supply chain (§32) | CI actions pinned by SHA, service images by digest, kcode by version and SHA-256; SBOM generator, audit of the runtime and the toolchain, secret scan; `composer.lock` committed against kariricode/contract 2.12.0 |
| Real databases | pass: `tests/Integration/Server` against PostgreSQL 16 and MariaDB 10.11, locally and in CI; MySQL 8 is covered only by the unit tests of its converter and platform |
