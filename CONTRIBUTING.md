# Contributing

## Setup

PHP 8.4 with `pdo_sqlite`; PCOV or Xdebug for the mutation gate.

```bash
composer install
kcode init        # the toolchain pinned in devkit.php (KaririCode Devkit, ADR-016)
composer check
```

Install [kcode](https://github.com/KaririCode-PHP/devkit) once:
`wget https://github.com/KaririCode-PHP/devkit/releases/latest/download/kcode.phar && chmod +x kcode.phar && sudo mv kcode.phar /usr/local/bin/kcode`.

## Gates (KaririCode Spec V4.3.3 §29.1)

| Script | Passes when |
|---|---|
| `composer phpstan` | level max on `src`, `tests` and `tools`, 0 errors |
| `composer psalm` | errorLevel 1, 0 errors |
| `composer cs-check` | no file to fix (`@PER-CS3.0`) |
| `composer complexity` | cyclomatic ≤ 10, cognitive ≤ 15, nesting ≤ 3, parameters ≤ 4, named arguments at 3+ arguments; exceptions only with a written reason in `tools/complexity-baseline.json` |
| `composer test` / `composer test-conformance` | green; risky tests, warnings, notices and deprecations fail |
| `composer infection` | MSI and covered MSI ≥ 60, the measured floor; the Spec target is 80 (ADR-016) |
| `composer security` | valid manifest, no advisories in the runtime or the toolchain, no secrets |

Update `docs/quality/QD-V433-COMPLIANCE.md` when a result changes.

## Rules

- `declare(strict_types=1)`; one type per file; concrete classes `final`; value objects `final readonly`.
- No `*Manager`, `*Helper`, `*Util` or `*Interface` names; no zero-argument `getX()`/`setX()`; no static properties; no `method_exists()` duck typing: type against `kariricode/contract` or a PSR interface.
- Exceptions: named factories only (private constructors), one contract family per class, a code in 6000–6199 with a `[DB_nnnn]` prefix, and messages that never echo SQL, driver text, DSNs or values (E-047).
- Section markers are exactly 72 characters measured from `//`.

## Adding an error code

Pick the family first (it decides the HTTP mapping), take the next free number in the class block, add a named factory whose message is a literal, then regenerate SPEC §12. `ExceptionConformanceTest` checks range, uniqueness and prefix.

## Decisions

Architectural changes need an ADR in `docs/adr/` (Context, Decision, Alternatives Considered, Consequences, References). Every bug fix ships with a test that fails without it.
