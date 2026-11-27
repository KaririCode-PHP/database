<?php

declare(strict_types=1);

namespace KaririCode\Database\Exception;

use KaririCode\Contract\Exception\ExceptionInterface;

// ── DatabaseException ────────────────────────────────────────────────

/**
 * Marker for every exception thrown by kariricode/database, so callers can
 * catch the whole package. Each concrete exception also implements exactly
 * one kariricode/contract family, which the application edge maps to a
 * response (Validation 422, NotFound 404, Protocol 400, Configuration 500,
 * Runtime 503/500).
 *
 * Preconditions: none.
 * Side effects: none.
 * Thread-safety: not applicable (interface).
 *
 * Error-code range: 6000–6199 (ecosystem registry; Database SPEC §11).
 *
 * Example:
 *   try { $connection->executeStatement($sql, $params); }
 *   catch (DatabaseException $e) { $logger->error($e->getMessage(), ['exception' => $e]); }
 *
 * @see https://www.rfc-editor.org/rfc/rfc9110
 * @since 4.3.0 (was an abstract class extending \RuntimeException)
 */
interface DatabaseException extends ExceptionInterface {}
