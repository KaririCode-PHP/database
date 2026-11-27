<?php

declare(strict_types=1);

namespace KaririCode\Database\Exception;

use KaririCode\Contract\Exception\Configuration;

/**
 * The connection or type configuration is wrong: credentials the server
 * rejects, a database it does not have, an unparsable DSN, an unknown
 * driver, scheme, charset or type name. Retrying does not help.
 *
 * Preconditions: none; instances come only from the named factories (the
 * constructor is private).
 *
 * Thread-safety: immutable after construction.
 *
 * Side effects: none.
 *
 * Family: Contract\Exception\Configuration. Messages carry the [DB_nnnn]
 * code and never echo SQL, driver text, DSNs or values (E-047); the driver
 * error stays on getPrevious().
 *
 * Example:
 *
 *     throw ConfigurationException::invalidDsn();
 *
 * References: Spec V4.3.3 §8.4; ADR-004; SPEC-001 §12 (codes 6012–6020,
 * 6121).
 *
 * @see https://www.rfc-editor.org/rfc/rfc9110
 * @since 4.3.0
 */
final class ConfigurationException extends \RuntimeException implements DatabaseException, Configuration
{
    private function __construct(string $message, int $code, ?\Throwable $previous = null)
    {
        parent::__construct(message: $message, code: $code, previous: $previous);
    }

    public static function authenticationFailed(?\Throwable $previous = null): self
    {
        return new self(message: '[DB_6012] The database server rejected the configured credentials.', code: 6012, previous: $previous);
    }

    public static function databaseNotFound(?\Throwable $previous = null): self
    {
        return new self(message: '[DB_6013] The configured database does not exist on the server.', code: 6013, previous: $previous);
    }

    /** The DSN can carry credentials, so it never enters the message (CWE-532). */
    public static function invalidDsn(): self
    {
        return new self(message: '[DB_6016] Invalid DSN: the value could not be parsed as a URI.', code: 6016, previous: null);
    }

    public static function unknownDsnScheme(): self
    {
        return new self(message: '[DB_6017] Unknown DSN scheme.', code: 6017, previous: null);
    }

    public static function connectionNotConfigured(): self
    {
        return new self(message: '[DB_6018] No connection is configured under the requested name.', code: 6018, previous: null);
    }

    public static function invalidCharset(): self
    {
        return new self(message: '[DB_6019] Invalid charset: only letters, digits, hyphen and underscore are allowed.', code: 6019, previous: null);
    }

    public static function unknownDriver(): self
    {
        return new self(message: '[DB_6020] Unknown driver: the supported drivers are pdo_mysql, pdo_pgsql and pdo_sqlite.', code: 6020, previous: null);
    }

    public static function unknownType(): self
    {
        return new self(message: '[DB_6121] No type converter is registered under the requested name.', code: 6121, previous: null);
    }
}
