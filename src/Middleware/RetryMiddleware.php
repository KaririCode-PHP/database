<?php

declare(strict_types=1);

namespace KaririCode\Database\Middleware;

use KaririCode\Contract\Resilience\RetryPolicy;
use KaririCode\Database\Contract\Driver;
use KaririCode\Database\Contract\DriverConnection;
use KaririCode\Database\Contract\Middleware;
use KaririCode\Database\Contract\Result;
use KaririCode\Database\Contract\Statement;
use KaririCode\Database\Exception\ConnectionException;
use KaririCode\Database\Resilience\TransientFailureRetryPolicy;
use KaririCode\Database\ValueObject\ConnectionParams;

/**
 * Retries a connect that failed for a transient reason (refused, lost or
 * full server) with backoff from a RetryPolicy. Only the connect is
 * retried: no statement has run yet, so a retry cannot repeat a write.
 *
 * Preconditions: wrap TenancyMiddleware (ADR-002), so each attempt runs
 * the tenant switch again. Wrong credentials and an unknown database are
 * ConfigurationException and are not retried.
 *
 * Thread-safety: stateless; safe to share.
 *
 * Side effects: sleeps between attempts (usleep()).
 *
 * Example:
 *
 *     $factory = new ConnectionFactory([new RetryMiddleware(new TransientFailureRetryPolicy(maxAttempts: 5))]);
 *
 * References: ADR-002; Spec V4.3.3 §31 (Contract\Resilience\RetryPolicy).
 *
 * @see https://aws.amazon.com/blogs/architecture/exponential-backoff-and-jitter/
 */
final class RetryMiddleware implements Middleware
{
    public function __construct(
        private readonly RetryPolicy $policy = new TransientFailureRetryPolicy(),
    ) {}

    #[\Override]
    public function wrap(Driver $driver): Driver
    {
        return new class ($driver, $this->policy) implements Driver {
            public function __construct(
                private readonly Driver $inner,
                private readonly RetryPolicy $policy,
            ) {}

            /**
             * Retenta so o estabelecimento da conexao: nao ha transacao aberta,
             * entao repetir nao duplica escrita (licao 14 do handoff do ORM).
             */
            #[\Override]
            public function connect(ConnectionParams $params): DriverConnection
            {
                for ($attempt = 1; ; ++$attempt) {
                    try {
                        return $this->inner->connect($params);
                    } catch (ConnectionException $e) {
                        if (!$this->policy->shouldRetry($e, $attempt)) {
                            throw $e;
                        }

                        usleep($this->policy->delayMilliseconds($e, $attempt) * 1000);
                    }
                }
            }
        };
    }
}
