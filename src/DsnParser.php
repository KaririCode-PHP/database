<?php

declare(strict_types=1);

namespace KaririCode\Database;

use KaririCode\Database\Exception\ConfigurationException;
use KaririCode\Database\Exception\ConnectionException;
use KaririCode\Database\ValueObject\ConnectionParams;

// ── DsnParser ────────────────────────────────────────────────────────
//
// Parses DATABASE_URL strings into ConnectionParams.
// Follows RFC 3986 URI syntax: driver://user:password@host:port/database?options
//
// Ref: Doctrine DBAL 4.4 DsnParser, Laravel DATABASE_URL support.
// ─────────────────────────────────────────────────────────────────────

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
        $parts = parse_url($dsn);

        if ($parts === false || !isset($parts['scheme'])) {
            throw ConfigurationException::invalidDsn();
        }

        $scheme = strtolower($parts['scheme']);
        $driver = $this->resolveDriver($scheme);

        $query = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $database = isset($parts['path'])
            ? ltrim($parts['path'], '/')
            : '';

        return new ConnectionParams(
            driver: $driver,
            host: $parts['host'] ?? 'localhost',
            port: $parts['port'] ?? $this->defaultPort($driver),
            database: $database,
            username: isset($parts['user']) ? urldecode($parts['user']) : '',
            password: isset($parts['pass']) ? urldecode($parts['pass']) : '',
            charset: $this->charset($query),
            options: $this->options($query),
        );
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
            'pdo_sqlite' => 0,
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
