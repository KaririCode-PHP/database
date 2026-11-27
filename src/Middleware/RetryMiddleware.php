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

// ── RetryMiddleware ──────────────────────────────────────────────────
//
// Retries on transient failures: deadlock and connectionLost.
// Must wrap TenancyMiddleware so reconnections re-execute
// SET search_path (see ADR-002 ordering constraint).
// ─────────────────────────────────────────────────────────────────────

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
