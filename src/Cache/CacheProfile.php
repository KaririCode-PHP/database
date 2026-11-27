<?php

declare(strict_types=1);

namespace KaririCode\Database\Cache;

use Psr\SimpleCache\CacheInterface;

// ── CacheProfile ─────────────────────────────────────────────────────
//
// Configuration for per-query result caching.
// Ref: Doctrine DBAL 4.4 QueryCacheProfile.
// ─────────────────────────────────────────────────────────────────────

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
