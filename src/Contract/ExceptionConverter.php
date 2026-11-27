<?php

declare(strict_types=1);

namespace KaririCode\Database\Contract;

use KaririCode\Database\Exception\DatabaseException;

/**
 * Maps a driver error to the package exception that carries its meaning
 * (unique violation, deadlock, wrong credentials), so callers catch
 * contract families instead of reading vendor codes.
 *
 * Preconditions: any \Throwable is accepted. The SQL is context for an
 * implementation and never reaches a message (E-047).
 *
 * Thread-safety: implementations are stateless.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     throw $converter->convert($pdoException, $sql);
 *
 * References: ADR-004 (semantic exception conversion); SPEC-001 §3.1.
 *
 * @see https://www.postgresql.org/docs/current/errcodes-appendix.html
 */
interface ExceptionConverter
{
    public function convert(\Throwable $exception, ?string $sql = null): DatabaseException;
}
