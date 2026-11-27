<?php

declare(strict_types=1);

namespace KaririCode\Database\Query;

// ── CompositeExpression ──────────────────────────────────────────────
//
// AND/OR composition of expressions. Stringable for use in WHERE.
// ─────────────────────────────────────────────────────────────────────

final class CompositeExpression implements \Stringable
{
    /** @param array<int, string|self> $parts */
    private function __construct(
        private readonly string $type,
        private readonly array $parts,
    ) {}

    public static function and(string|self ...$parts): self
    {
        return new self('AND', array_values($parts));
    }

    public static function or(string|self ...$parts): self
    {
        return new self('OR', array_values($parts));
    }

    #[\Override]
    public function __toString(): string
    {
        if (count($this->parts) === 1) {
            return (string) $this->parts[0];
        }

        $joined = implode(" {$this->type} ", array_map('strval', $this->parts));

        return "({$joined})";
    }
}
