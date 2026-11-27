<?php

declare(strict_types=1);

namespace KaririCode\Database\Exception;

use KaririCode\Contract\Exception\ExceptionInterface;

/**
 * Marker for every exception thrown by kariricode/database, so callers can
 * catch the whole package. Each concrete exception also implements exactly
 * one kariricode/contract family, which the application edge maps to a
 * response (Validation 422, NotFound 404, Protocol 400, Configuration 500,
 * Runtime 503/500).
 *
 * Preconditions: none.
 *
 * Thread-safety: not applicable (interface).
 *
 * Side effects: none.
 *
 * Error-code range: 6000–6199 (ecosystem registry).
 *
 * Example:
 *
 *     try {
 *         $connection->executeStatement($sql, $params);
 *     } catch (DatabaseException $e) {
 *         $logger->error($e->getMessage(), ['exception' => $e]);
 *     }
 *
 * References: Spec V4.3.3 §8.4 (families); ADR-004; SPEC-001 §12 (codes).
 *
 * @see https://www.rfc-editor.org/rfc/rfc9110
 * @since 4.3.0 (was an abstract class extending \RuntimeException)
 */
interface DatabaseException extends ExceptionInterface {}
