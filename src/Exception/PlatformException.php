<?php

declare(strict_types=1);

namespace KaririCode\Database\Exception;

use KaririCode\Contract\Exception\Configuration;

// ── PlatformException ────────────────────────────────────────────────

/**
 * The selected platform (vendor and version) lacks a feature the call needs.
 *
 * Preconditions: none; instances come only from the named factories
 *   below (the constructor is private).
 * Side effects: none.
 * Thread-safety: immutable after construction.
 *
 * Family: Contract\Exception\Configuration (Spec V4.3.3 §8.4). Messages carry
 * the [DB_nnnn] code and never echo SQL, driver text, DSNs or values
 * (E-047); the driver error stays on getPrevious().
 *
 * Example:
 *   throw PlatformException::savepointsNotSupported();
 *
 * @see https://www.rfc-editor.org/rfc/rfc9110 (status mapping at the edge)
 * @since 4.3.0
 */
final class PlatformException extends \RuntimeException implements DatabaseException, Configuration
{
    private function __construct(string $message, int $code, ?\Throwable $previous = null)
    {
        parent::__construct(message: $message, code: $code, previous: $previous);
    }

    /** $feature is a name chosen by this package (for example "RETURNING"), never caller input. */
    public static function unsupportedFeature(string $feature): self
    {
        return new self("[DB_6080] This platform does not support the feature: {$feature}.", 6080);
    }

    public static function savepointsNotSupported(): self
    {
        return new self(message: '[DB_6081] This platform does not support savepoints.', code: 6081, previous: null);
    }

    public static function schemasNotSupported(): self
    {
        return new self(message: '[DB_6082] This platform does not support schemas.', code: 6082, previous: null);
    }

    public static function advisoryLocksNotSupported(): self
    {
        return new self(message: '[DB_6083] This platform does not support advisory locks.', code: 6083, previous: null);
    }

    public static function unknownColumnType(): self
    {
        return new self(message: '[DB_6084] Unknown column type.', code: 6084, previous: null);
    }
}
