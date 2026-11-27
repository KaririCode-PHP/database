<?php

declare(strict_types=1);

namespace KaririCode\Database;

use KaririCode\Database\Exception\ConfigurationException;
use KaririCode\Database\ValueObject\ConnectionParams;

/**
 * Reads a DATABASE_URL into ConnectionParams, so one environment variable
 * configures a connection: mysql://user:pass@host:3306/shop?charset=utf8mb4.
 * The scheme picks the driver (mysql, mariadb, pgsql, postgres,
 * postgresql, sqlite, or a pdo_* name, plus custom aliases); the user name
 * and password are URL-decoded; the query string gives the charset and
 * free options. For SQLite only the first slash after "sqlite://" is a
 * separator: sqlite:////var/app.db is absolute, sqlite:///app.db relative
 * and sqlite:///:memory: in memory.
 *
 * Preconditions: a DSN starts with a scheme; a password with "@", ":" or
 * "/" is percent-encoded. A custom alias may map to any name, and an
 * unsupported one fails later, in ConnectionParams::driverType() (6020).
 *
 * Thread-safety: immutable; safe to share.
 *
 * Side effects: none. The password is never part of an error message.
 *
 * Example:
 *
 *     $params = (new DsnParser())->parse((string) getenv('DATABASE_URL'));
 *     $connection = (new ConnectionFactory())->createConnection($params);
 *
 * References: ADR-011 (DSN parser); SPEC-001 §7.
 *
 * @see https://www.rfc-editor.org/rfc/rfc3986
 */
final class DsnParser
{
    private const SCHEME_MAP = [
        'mysql' => 'pdo_mysql',
        'mariadb' => 'pdo_mysql',
        'pgsql' => 'pdo_pgsql',
        'postgres' => 'pdo_pgsql',
        'postgresql' => 'pdo_pgsql',
        'sqlite' => 'pdo_sqlite',
        'pdo_mysql' => 'pdo_mysql',
        'pdo_pgsql' => 'pdo_pgsql',
        'pdo_sqlite' => 'pdo_sqlite',
    ];

    /** @param array<string, string> $customSchemes */
    public function __construct(
        private readonly array $customSchemes = [],
    ) {}

    public function parse(string $dsn): ConnectionParams
    {
        if (preg_match('/\A([a-zA-Z][a-zA-Z0-9+._-]*):/', $dsn, $scheme) !== 1) {
            throw ConfigurationException::invalidDsn();
        }

        $driver = $this->resolveDriver(strtolower($scheme[1]));

        return $driver === 'pdo_sqlite'
            ? $this->parseSqlite(substr($dsn, strlen($scheme[0])))
            : $this->parseServer($dsn, $driver);
    }

    private function parseServer(string $dsn, string $driver): ConnectionParams
    {
        // parse_url() recusa a autoridade vazia (mysql:///shop): ela e o localhost.
        $parts = parse_url((string) preg_replace('#\A([a-zA-Z][a-zA-Z0-9+._-]*:)///#', '$1//localhost/', $dsn));

        if ($parts === false) {
            throw ConfigurationException::invalidDsn();
        }

        $query = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        return new ConnectionParams(
            driver: $driver,
            host: $parts['host'] ?? 'localhost',
            port: $parts['port'] ?? $this->defaultPort($driver),
            database: self::withoutFirstSlash($parts['path'] ?? ''),
            username: isset($parts['user']) ? urldecode($parts['user']) : '',
            password: isset($parts['pass']) ? urldecode($parts['pass']) : '',
            charset: $this->charset($query),
            options: $this->options($query),
        );
    }

    /**
     * parse_url() rejects "sqlite:///path" (an empty authority), so the
     * path is read here: "//" is the empty authority, then the first "/".
     */
    private function parseSqlite(string $rest): ConnectionParams
    {
        [$path, $queryString] = array_pad(explode('?', $rest, 2), 2, '');
        $query = [];
        parse_str($queryString, $query);

        return new ConnectionParams(
            driver: 'pdo_sqlite',
            port: 0,
            database: rawurldecode(self::withoutFirstSlash(str_starts_with($path, '//') ? substr($path, 2) : $path)),
            charset: $this->charset($query),
            options: $this->options($query),
        );
    }

    private static function withoutFirstSlash(string $path): string
    {
        return str_starts_with($path, '/') ? substr($path, 1) : $path;
    }

    private function resolveDriver(string $scheme): string
    {
        if (isset($this->customSchemes[$scheme])) {
            return $this->customSchemes[$scheme];
        }

        if (isset(self::SCHEME_MAP[$scheme])) {
            return self::SCHEME_MAP[$scheme];
        }

        throw ConfigurationException::unknownDsnScheme();
    }

    private function defaultPort(string $driver): int
    {
        return match ($driver) {
            'pdo_mysql' => 3306,
            'pdo_pgsql' => 5432,
            default => 0,
        };
    }

    /** @param array<array-key, mixed> $query */
    private function charset(array $query): string
    {
        return isset($query['charset']) && is_string($query['charset']) ? $query['charset'] : 'utf8mb4';
    }

    /**
     * Opcoes so com chave textual: parse_str() pode produzir chaves inteiras.
     *
     * @param array<array-key, mixed> $query
     * @return array<string, mixed>
     */
    private function options(array $query): array
    {
        // O filtro garante chaves textuais; o Psalm nao estreita chaves via array_filter.
        /** @var array<string, mixed> $options */
        $options = array_filter($query, 'is_string', ARRAY_FILTER_USE_KEY);

        return $options;
    }
}
