<?php

declare(strict_types=1);

namespace KaririCode\Database\Exception;

use KaririCode\Contract\Exception\Runtime;

// ── TransactionException ─────────────────────────────────────────────

/**
 * A transaction, savepoint or advisory-lock operation could not complete.
 *
 * Preconditions: none; instances come only from the named factories
 *   below (the constructor is private).
 * Side effects: none.
 * Thread-safety: immutable after construction.
 *
 * Family: Contract\Exception\Runtime (Spec V4.3.3 §8.4). Messages carry
 * the [DB_nnnn] code and never echo SQL, driver text, DSNs or values
 * (E-047); the driver error stays on getPrevious().
 *
 * Example:
 *   throw TransactionException::noActiveTransaction();
 *
 * @see https://www.rfc-editor.org/rfc/rfc9110 (status mapping at the edge)
 * @since 4.3.0
 */
final class TransactionException extends \RuntimeException implements DatabaseException, Runtime
{
    private function __construct(string $message, int $code, ?\Throwable $previous = null)
    {
        parent::__construct(message: $message, code: $code, previous: $previous);
    }

    public static function noActiveTransaction(): self
    {
        return new self(message: '[DB_6060] There is no active transaction.', code: 6060, previous: null);
    }

    public static function savepointFailed(?\Throwable $previous = null): self
    {
        return new self(message: '[DB_6061] A savepoint operation failed.', code: 6061, previous: $previous);
    }

    public static function commitFailed(?\Throwable $previous = null): self
    {
        return new self(message: '[DB_6062] The transaction could not be committed.', code: 6062, previous: $previous);
    }

    /** Fails closed: the caller never continues without the lock. */
    public static function advisoryLockNotAcquired(): self
    {
        return new self(message: '[DB_6063] The advisory lock was not acquired; another session holds it.', code: 6063, previous: null);
    }
}
