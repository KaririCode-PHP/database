<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Integration\Sqlite;

use KaririCode\Database\Cache\CacheProfile;
use KaririCode\Database\Event\QueryExecuted;
use KaririCode\Database\Event\TransactionBegun;
use KaririCode\Database\Event\TransactionCommitted;
use KaririCode\Database\Middleware\LoggingMiddleware;
use KaririCode\Database\Tests\Support\ArrayCache;
use KaririCode\Database\Tests\Support\RecordingDispatcher;
use KaririCode\Database\Tests\Support\RecordingLogger;
use KaririCode\Database\Tests\Support\Sqlite;
use PHPUnit\Framework\TestCase;

/** PSR-16 (cache), PSR-14 (eventos) e PSR-3 (log) no lugar das pontes duck-typed. */
final class PsrPortsTest extends TestCase
{
    public function testResultCacheHitsAfterMissAndKeepsEmptyResults(): void
    {
        $events = new RecordingDispatcher();
        $c = Sqlite::connection([], $events);
        $cache = new ArrayCache();
        $c->executeStatement('CREATE TABLE t (id INTEGER)');
        $profile = new CacheProfile(ttl: 60, cache: $cache);
        $before = $this->queries($events);
        $first = $c->executeCacheQuery('SELECT id FROM t', [], [], $profile)->fetchAllAssociative();
        $second = $c->executeCacheQuery('SELECT id FROM t', [], [], $profile)->fetchAllAssociative();
        self::assertCount(0, $first);
        self::assertCount(0, $second);
        self::assertSame(1, $this->queries($events) - $before, 'um array vazio guardado e um acerto, nao uma nova consulta');
        self::assertSame(60, $cache->lastTtl);
    }

    public function testEventsGoThroughPsr14(): void
    {
        $events = new RecordingDispatcher();
        $c = Sqlite::connection([], $events);
        $c->beginTransaction();
        $c->executeStatement('CREATE TABLE t (id INTEGER)');
        $c->commit();
        $classes = array_map(fn(object $e): string => $e::class, $events->events);
        self::assertContains(TransactionBegun::class, $classes);
        self::assertContains(TransactionCommitted::class, $classes);
        self::assertLessThan(array_search(TransactionCommitted::class, $classes, true), array_search(TransactionBegun::class, $classes, true));
    }

    public function testLoggingMiddlewareNeverLogsParameterValues(): void
    {
        $logger = new RecordingLogger();
        $c = Sqlite::connection([new LoggingMiddleware($logger)]);
        $c->executeQuery('SELECT ? AS secret', ['s3cr3t-value'])->fetchOne();
        self::assertStringNotContainsString('s3cr3t-value', serialize($logger->records));
        $counts = array_column(array_column($logger->records, 2), 'parameter_count');
        self::assertContains(1, $counts);
    }

    private function queries(RecordingDispatcher $events): int
    {
        return count(array_filter($events->events, fn(object $e): bool => $e instanceof QueryExecuted));
    }
}
