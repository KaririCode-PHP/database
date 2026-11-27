<?php

declare(strict_types=1);

namespace KaririCode\Database\Middleware;

use KaririCode\Database\Contract\Driver;
use KaririCode\Database\Contract\DriverConnection;
use KaririCode\Database\Contract\Middleware;
use KaririCode\Database\Contract\Result;
use KaririCode\Database\Contract\Statement;
use KaririCode\Database\ValueObject\ConnectionParams;
use KaririCode\Database\ValueObject\QueryResult;

/**
 * Records every statement as a QueryResult (SQL, parameters passed to
 * execute(), duration, row count) and calls back on slow ones, for debug
 * toolbars and slow-query alerts.
 *
 * Preconditions: the threshold is in milliseconds. Values bound one by one
 * are not captured, only those passed to execute().
 *
 * Thread-safety: collects profiles; one instance per request, or reset()
 * between requests in a long-running worker, since the list only grows.
 *
 * Side effects: keeps every profile in memory and calls the callback.
 *
 * Example:
 *
 *     $profiler = new ProfilingMiddleware(250.0, static fn(QueryResult $slow) => $logger->warning($slow->sql));
 *     $factory = new ConnectionFactory([$profiler]);
 *
 * References: ADR-002; SPEC-001 §2.1.
 *
 * @see https://www.php.net/manual/en/function.hrtime.php
 */
final class ProfilingMiddleware implements Middleware
{
    /** @var array<int, QueryResult> */
    private array $profiles = [];

    private float $slowQueryThresholdMs;

    /** @param ?callable(QueryResult): void $slowQueryCallback */
    public function __construct(
        float $slowQueryThresholdMs = 100.0,
        private readonly mixed $slowQueryCallback = null,
    ) {
        $this->slowQueryThresholdMs = $slowQueryThresholdMs;
    }

    /** @return array<int, QueryResult> */
    public function profiles(): array
    {
        return $this->profiles;
    }

    public function reset(): void
    {
        $this->profiles = [];
    }

    #[\Override]
    public function wrap(Driver $driver): Driver
    {
        $middleware = $this;

        return new class ($driver, $middleware) implements Driver {
            public function __construct(
                private readonly Driver $inner,
                private readonly ProfilingMiddleware $middleware,
            ) {}

            #[\Override]
            public function connect(ConnectionParams $params): DriverConnection
            {
                $conn = $this->inner->connect($params);

                return new class ($conn, $this->middleware) implements DriverConnection {
                    public function __construct(
                        private readonly DriverConnection $inner,
                        private readonly ProfilingMiddleware $middleware,
                    ) {}

                    #[\Override]
                    public function prepare(string $sql): Statement
                    {
                        return new class (inner: $this->inner->prepare($sql), sql: $sql, middleware: $this->middleware) implements Statement {
                            public function __construct(
                                private readonly Statement $inner,
                                private readonly string $sql,
                                private readonly ProfilingMiddleware $middleware,
                            ) {}

                            #[\Override]
                            public function bindValue(int|string $param, mixed $value, int $type = 2): void
                            {
                                $this->inner->bindValue(param: $param, value: $value, type: $type);
                            }

                            /**
                             * @param array<int|string, mixed> $params
                             */
                            #[\Override]
                            public function execute(?array $params = null): Result
                            {
                                $start = hrtime(true);
                                $result = $this->inner->execute($params);
                                $ms = (hrtime(true) - $start) / 1_000_000;
                                $this->middleware->record(sql: $this->sql, params: $params ?? [], ms: $ms, rowCount: $result->rowCount());

                                return $result;
                            }
                        };
                    }

                    #[\Override]
                    public function exec(string $sql): int
                    {
                        $start = hrtime(true);
                        $affected = $this->inner->exec($sql);
                        $ms = (hrtime(true) - $start) / 1_000_000;
                        $this->middleware->record(sql: $sql, params: [], ms: $ms, rowCount: $affected);

                        return $affected;
                    }

                    #[\Override]
                    public function lastInsertId(?string $name = null): string
                    {
                        return $this->inner->lastInsertId($name);
                    }
                    #[\Override]
                    public function beginTransaction(): void
                    {
                        $this->inner->beginTransaction();
                    }
                    #[\Override]
                    public function commit(): void
                    {
                        $this->inner->commit();
                    }
                    #[\Override]
                    public function rollBack(): void
                    {
                        $this->inner->rollBack();
                    }
                    #[\Override]
                    public function nativeConnection(): object
                    {
                        return $this->inner->nativeConnection();
                    }
                };
            }
        };
    }

    /**
     * @internal Used by decorated classes
     * @param array<int|string, mixed> $params
     */
    public function record(string $sql, array $params, float $ms, int $rowCount): void
    {
        $profile = new QueryResult(sql: $sql, params: $params, executionTimeMs: $ms, rowCount: $rowCount);
        $this->profiles[] = $profile;

        if ($ms >= $this->slowQueryThresholdMs && $this->slowQueryCallback !== null) {
            ($this->slowQueryCallback)($profile);
        }
    }
}
