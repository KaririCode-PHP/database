<?php

declare(strict_types=1);

namespace KaririCode\Database\Exception;

use KaririCode\Contract\Exception\Runtime;

/**
 * A statement failed: syntax or access error, constraint violation,
 * deadlock, lock timeout, a builder in an invalid state or a missing
 * column. The SQLSTATE of the driver error is exposed as $sqlState.
 *
 * Preconditions: none; instances come only from the named factories (the
 * constructor is private).
 *
 * Thread-safety: immutable after construction.
 *
 * Side effects: none.
 *
 * Family: Contract\Exception\Runtime. Messages carry the [DB_nnnn] code
 * and never echo SQL, driver text or values (E-047); the driver error
 * stays on getPrevious().
 *
 * Example:
 *
 *     try {
 *         $connection->insert('users', ['email' => $email]);
 *     } catch (QueryException $e) {
 *         $taken = $e->getCode() === 6032;   // unique violation
 *     }
 *
 * References: Spec V4.3.3 §8.4; ADR-004; SPEC-001 §12 (codes 6030–6041,
 * 6102).
 *
 * @see https://www.postgresql.org/docs/current/errcodes-appendix.html
 * @since 4.3.0
 */
final class QueryException extends \RuntimeException implements DatabaseException, Runtime
{
    /** SQLSTATE of the driver error, when the previous exception carries one. */
    public readonly ?string $sqlState;

    private function __construct(string $message, int $code, ?\Throwable $previous = null)
    {
        parent::__construct(message: $message, code: $code, previous: $previous);
        $this->sqlState = $previous instanceof \PDOException ? (string) $previous->getCode() : null;
    }

    public static function syntaxError(?\Throwable $previous = null): self
    {
        return new self(message: '[DB_6030] The statement has a syntax error or violates an access rule.', code: 6030, previous: $previous);
    }

    public static function constraintViolation(?\Throwable $previous = null): self
    {
        return new self(message: '[DB_6031] An integrity constraint was violated.', code: 6031, previous: $previous);
    }

    public static function uniqueViolation(?\Throwable $previous = null): self
    {
        return new self(message: '[DB_6032] A unique constraint was violated.', code: 6032, previous: $previous);
    }

    public static function foreignKeyViolation(?\Throwable $previous = null): self
    {
        return new self(message: '[DB_6033] A foreign key constraint was violated.', code: 6033, previous: $previous);
    }

    public static function notNullViolation(?\Throwable $previous = null): self
    {
        return new self(message: '[DB_6034] A NOT NULL constraint was violated.', code: 6034, previous: $previous);
    }

    public static function deadlock(?\Throwable $previous = null): self
    {
        return new self(message: '[DB_6035] The transaction was chosen as a deadlock victim.', code: 6035, previous: $previous);
    }

    public static function lockTimeout(?\Throwable $previous = null): self
    {
        return new self(message: '[DB_6036] Lock wait timeout exceeded.', code: 6036, previous: $previous);
    }

    public static function invalidParameterCount(?\Throwable $previous = null): self
    {
        return new self(message: '[DB_6037] The bound parameters do not match the statement placeholders.', code: 6037, previous: $previous);
    }

    public static function prepareFailed(?\Throwable $previous = null): self
    {
        return new self(message: '[DB_6038] The statement could not be prepared.', code: 6038, previous: $previous);
    }

    public static function executeFailed(?\Throwable $previous = null): self
    {
        return new self(message: '[DB_6039] The statement failed to execute.', code: 6039, previous: $previous);
    }

    public static function invalidBuilderState(): self
    {
        return new self(message: '[DB_6040] The query builder is in an invalid state.', code: 6040, previous: null);
    }

    public static function introspectionFailed(?\Throwable $previous = null): self
    {
        return new self(message: '[DB_6102] Schema introspection failed.', code: 6102, previous: $previous);
    }

    /** A linha nao tem uma coluna que a leitura exige: forma inesperada da consulta. */
    public static function missingColumn(): self
    {
        return new self(message: '[DB_6041] The result row lacks an expected column.', code: 6041);
    }
}
