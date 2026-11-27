<?php

declare(strict_types=1);

namespace KaririCode\Database\Resilience;

use KaririCode\Contract\Resilience\RetryPolicy;
use KaririCode\Database\Exception\ConnectionException;
use KaririCode\Database\Exception\QueryException;

// ── TransientFailureRetryPolicy ──────────────────────────────────────

/**
 * Decides which database failures are worth another attempt and how long
 * to wait: connection failures, deadlocks (6035) and lock timeouts (6036).
 * Configuration errors (bad credentials, unknown database) never retry.
 *
 * Preconditions: $maxAttempts >= 1; delays >= 0 (negative values act as 0).
 * Side effects: delayMilliseconds() draws from random_int() (CSPRNG).
 * Thread-safety: immutable; safe to share.
 *
 * The delay uses "full jitter": uniform between 0 and the exponential
 * ceiling, which spreads retries from many clients (Brooker, 2015).
 *
 * Example:
 *   $retry = new RetryMiddleware(new TransientFailureRetryPolicy(maxAttempts: 5));
 *
 * Spec V4.3.3 §31: typed against Contract\Resilience\RetryPolicy (contract 2.10.0).
 * @see https://aws.amazon.com/blogs/architecture/exponential-backoff-and-jitter/
 * @since 4.3.0
 */
final readonly class TransientFailureRetryPolicy implements RetryPolicy
{
    public function __construct(
        private int $maxAttempts = 3,
        private int $baseDelayMilliseconds = 100,
        private int $maxDelayMilliseconds = 2_000,
    ) {}

    #[\Override]
    public function shouldRetry(\Throwable $error, int $attempt): bool
    {
        return $attempt < $this->maxAttempts && $this->isTransient($error);
    }

    #[\Override]
    public function delayMilliseconds(\Throwable $error, int $attempt): int
    {
        $exponential = $this->baseDelayMilliseconds * (1 << min(max($attempt - 1, 0), 20));
        $ceiling = max(0, min($this->maxDelayMilliseconds, $exponential));

        return random_int(0, $ceiling);
    }

    private function isTransient(\Throwable $error): bool
    {
        return $error instanceof ConnectionException
            || ($error instanceof QueryException && in_array($error->getCode(), [6035, 6036], true));
    }
}
