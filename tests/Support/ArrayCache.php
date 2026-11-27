<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Support;

use Psr\SimpleCache\CacheInterface;

/** Cache PSR-16 em memoria para testes; registra o TTL recebido. */
final class ArrayCache implements CacheInterface
{
    /** @var array<string, mixed> */
    public array $items = [];

    public mixed $lastTtl = 'unset';

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->items) ? $this->items[$key] : $default;
    }

    public function set(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        $this->items[$key] = $value;
        $this->lastTtl = $ttl;
        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->items[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->items = [];
        return true;
    }

    /** @param iterable<string> $keys */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        foreach ($keys as $k) {
            yield $k => $this->get($k, $default);
        }
    }

    /** @param iterable<string, mixed> $values */
    public function setMultiple(iterable $values, int|\DateInterval|null $ttl = null): bool
    {
        foreach ($values as $k => $v) {
            $this->set($k, $v, $ttl);
        } return true;
    }

    /** @param iterable<string> $keys */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $k) {
            $this->delete($k);
        } return true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->items);
    }
}
