<?php

declare(strict_types=1);

namespace KaririCode\Database\Exception;

use KaririCode\Contract\Exception\Configuration;

/**
 * The selected platform (vendor and version) lacks a feature the call
 * needs: savepoints, schemas, advisory locks, an isolation level.
 *
 * Preconditions: none; instances come only from the named factories (the
 * constructor is private).
 *
 * Thread-safety: immutable after construction.
 *
 * Side effects: none.
 *
 * Family: Contract\Exception\Configuration. Messages carry the [DB_nnnn]
 * code and name only the feature (E-047).
 *
 * Example:
 *
 *     throw PlatformException::savepointsNotSupported();
 *
 * References: Spec V4.3.3 §8.4; ADR-003; SPEC-001 §12 (codes 6080–6084).
 *
 * @see https://www.rfc-editor.org/rfc/rfc9110
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
