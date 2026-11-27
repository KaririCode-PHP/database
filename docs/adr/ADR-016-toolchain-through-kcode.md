# ADR-016: Quality gates through KaririCode Devkit, with the root configs

**Status:** Accepted
**Date:** 2026-11-27
**Authors:** Walmir Silva

## Context

The 4.4.0 reviewed on 2026-10-04 kept PHPUnit, PHPStan, Psalm, PHP-CS-Fixer and Infection in `require-dev`, plus PHPMD, which no script ran. The ecosystem now installs its toolchain with KaririCode Devkit, and Devkit 1.2.1 runs a component's committed configs (`'root_configs' => true`), so the §29.1 gates move without weakening.

The first run with a coverage driver also measured what QD-V433 could not: the mutation score is 60%, below the 80% of §19.4.

## Decision

- `require-dev` is removed. `devkit.php` pins PHPUnit 11.5.56, PHPStan 2.3.7, Psalm 6.19.1, PHP-CS-Fixer 3.95.27 and Infection 0.35.5. PHPMD and `phpmd.xml` are dropped: no gate used them.
- The root configs stay and are what kcode runs; paths into `vendor/` now point into `.kcode/vendor/`.
- `composer.lock` is committed with the runtime only: kariricode/contract 2.12.0 and the three PSR packages. `composer security` audits the lock and the toolchain.
- `composer infection` holds the measured floor, MSI and covered MSI ≥ 60, so the score cannot fall while the suite grows towards 80 (QD-V433, open item).
- CI installs kcode 1.2.1 by version and SHA-256, with the PDO extensions of the drivers.

## Alternatives Considered

| Alternative | Why not |
|---|---|
| Keep `require-dev` | the rest of the ecosystem installs the toolchain through kcode |
| Keep the 80% threshold | CI would be red on the first run; the gap is real and is recorded instead |
| Drop mutation from `composer check` | nothing would stop the score from falling |

## Consequences

- One toolchain model across the ecosystem.
- §19.4 is not met yet: 265 escaped mutants, most in `SqlQueryBuilder`, `DefaultConnection`, `TransientFailureRetryPolicy`, `LoggingMiddleware` and the exception converters. Raising the floor is part of the next release.

## Amendment — 2026-11-27: the floor goes back to 80

Tests written against the escaped mutants found two bugs (user `driverOptions` renumbered by the array spread in the three PDO drivers; `SqlQueryBuilder::values()` never bound its values), and the server suites added for PostgreSQL 16 and MariaDB 10.11 found the rest fixed in 4.4.0 (CHANGELOG). With both servers the score is 87%: 1409 mutants, 1210 killed, 15 errors, 2 time-outs, 182 escaped. `composer infection` no longer passes `--min-msi=60 --min-covered-msi=60`, so the 80% of §19.4 in `infection.json5` applies, and the open item of QD-V433 is closed.

CI runs the gates with both servers as service containers, so the mutation run covers the vendor code paths too; without them the server tests are skipped and the platform and inspector code of MySQL and PostgreSQL is mutated without tests that reach it. Most of the mutants left are equivalent: the `hrtime()` divisor of the timings, the `usleep()` of `RetryMiddleware`, version thresholds no supported server sits on, and vendor-table rows the SQLSTATE class already maps.
