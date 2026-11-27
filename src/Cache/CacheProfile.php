<?php

declare(strict_types=1);

namespace KaririCode\Database\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * How one query is cached by executeCacheQuery(): for how long, under which
 * key and in which PSR-16 store. The default key is a SHA-256 of the SQL,
 * the parameters and their types, so different parameters never share an
 * entry.
 *
 * Preconditions: ttl in seconds, 0 for the store's default lifetime; a
 * fixed key is the caller's to keep unique. Parameters are serialized only
 * to be hashed, never unserialized.
 *
 * Thread-safety: immutable.
 *
 * Side effects: none; the connection does the caching.
 *
 * Example:
 *
 *     $profile = new CacheProfile(ttl: 300, cache: $psr16Cache);
 *     $countries = $connection->executeCacheQuery('SELECT code, name FROM countries', [], [], $profile)->fetchAllKeyValue();
 *
 * References: ADR-010 (result cache); SPEC-001 §4.1.
 *
 * @see https://www.php-fig.org/psr/psr-16/
 */
final readonly class CacheProfile
{
    /**
     * @param int $ttl Cache lifetime in seconds
     * @param ?string $key Custom cache key (null = auto-generate from SQL+params hash)
     * @param ?CacheInterface $cache PSR-16 cache (null = use the connection default)
     */
    public function __construct(
        public int $ttl = 0,
        public ?string $key = null,
        public ?CacheInterface $cache = null,
    ) {}

    /**
     * @param array<int|string, int> $types
     * @param array<int|string, mixed> $params
     */
    public function generateCacheKey(string $sql, array $params, array $types): string
    {
        if ($this->key !== null) {
            return $this->key;
        }

        // serialize() so alimenta o hash e nunca e desserializado (o risco do CWE-502
        // e do unserialize). json_encode() devolvia false com bytes binarios
        // invalidos, e consultas com parametros diferentes dividiam a mesma chave.
        return hash('sha256', serialize([$sql, $params, $types]));
    }
}
