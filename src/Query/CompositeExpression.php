<?php

declare(strict_types=1);

namespace KaririCode\Database\Query;

/**
 * A group of predicates joined by AND or OR, which may nest; a group of
 * two or more renders in parentheses, so nesting keeps its precedence.
 *
 * Preconditions: parts are SQL predicates written in code.
 *
 * Thread-safety: immutable.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     (string) CompositeExpression::and('a = 1', CompositeExpression::or('b = 2', 'c = 3'));
 *     // (a = 1 AND (b = 2 OR c = 3))
 *
 * References: ADR-009; SPEC-001 §5.
 *
 * @see https://www.postgresql.org/docs/current/sql-syntax-lexical.html#SQL-PRECEDENCE
 */
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
