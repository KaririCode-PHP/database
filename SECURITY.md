# Security Policy

Report vulnerabilities privately to walmir.silva@kariricode.org, not in a public issue. Supported line: 4.3.x.

## Properties this package guarantees

- Values are always bound as parameters. Identifiers taken from configuration (schema names) are escaped; the PostgreSQL charset is validated before use.
- Exception messages never contain SQL, driver text, DSNs or values (Spec V4.3.3 E-047); `ConnectionParams` redacts the password in `__debugInfo()` and marks it `#[\SensitiveParameter]`.
- `LoggingMiddleware` logs the SQL template and the parameter count, never parameter values.
- An advisory lock that is not acquired throws; the caller never proceeds without it.
- Retries cover connection establishment only, so a transaction is never replayed.

## Supply chain (Spec V4.3.3 §32)

- CI actions are pinned by full commit SHA, not by tag (tags are mutable: CVE-2025-30066).
- `composer security` runs `composer validate --strict`, `composer audit` and `tools/secret-scan.php`.
- `composer sbom` writes a CycloneDX 1.5 SBOM.
- `composer.lock` will be committed once kariricode/contract 2.11.0 is tagged.
