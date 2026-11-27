<?php

declare(strict_types=1);

namespace KaririCode\Database\Event;

use KaririCode\Database\Contract\DataStoreEvent;

/**
 * A failed connect or a failed statement, as a PSR-14 event; for a
 * statement it also carries the SQL and the bound values.
 *
 * Preconditions: none.
 *
 * Thread-safety: immutable.
 *
 * Side effects: none, but listeners receive the bound values, which may
 * hold personal data or secrets: log them only after redaction.
 *
 * Example:
 *
 *     if ($event instanceof ConnectionError) {
 *         $logger->error('Database error.', ['exception' => $event->exception]);
 *     }
 *
 * References: ADR-013 (connection events); SPEC-001 §2.3.
 *
 * @see https://www.php-fig.org/psr/psr-14/
 */
final readonly class ConnectionError implements DataStoreEvent
{
    /**
     * @param array<int|string, mixed> $params
     */
    public function __construct(
        public \Throwable $exception,
        public ?string $sql = null,
        public array $params = [],
    ) {}
}
