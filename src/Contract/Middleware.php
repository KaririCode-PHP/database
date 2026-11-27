<?php

declare(strict_types=1);

namespace KaririCode\Database\Contract;

/**
 * Decorates a Driver to add behaviour around the connect and every
 * statement (logging, profiling, retrying the connect, switching the
 * tenant schema) without touching the drivers.
 *
 * Preconditions: wrap() returns a Driver that delegates to the one it
 * received. ConnectionFactory applies the list outermost first.
 *
 * Thread-safety: up to the implementation: LoggingMiddleware is
 * stateless, ProfilingMiddleware collects profiles.
 *
 * Side effects: those of the decoration.
 *
 * Example:
 *
 *     $factory = new ConnectionFactory([new LoggingMiddleware($logger), new TenancyMiddleware($resolveSchema)]);
 *
 * References: ADR-002 (middleware pipeline and order); SPEC-001 §2.1.
 *
 * @see https://en.wikipedia.org/wiki/Decorator_pattern
 */
interface Middleware
{
    public function wrap(Driver $driver): Driver;
}
