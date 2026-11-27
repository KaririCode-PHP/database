<?php

declare(strict_types=1);

namespace KaririCode\Database\Exception;

use KaririCode\Contract\Exception\Runtime;

/**
 * A transaction, savepoint or advisory-lock operation could not complete:
 * no active transaction, a failed savepoint or commit, a lock held by
 * another session.
 *
 * Preconditions: none; instances come only from the named factories (the
 * constructor is private).
 *
 * Thread-safety: immutable after construction.
 *
 * Side effects: none.
 *
 * Family: Contract\Exception\Runtime. Messages carry the [DB_nnnn] code
 * and never echo SQL or driver text (E-047); the driver error stays on
 * getPrevious().
 *
 * Example:
 *
 *     throw TransactionException::noActiveTransaction();
 *
 * References: Spec V4.3.3 §8.4; ADR-005; SPEC-001 §12 (codes 6060–6063).
 *
 * @see https://www.rfc-editor.org/rfc/rfc9110
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
