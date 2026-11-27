<?php

declare(strict_types=1);

namespace KaririCode\Database\Query;

/**
 * Writes predicate SQL for QueryBuilder clauses (eq, lt, like, in,
 * between, IS NULL) and groups them with AND and OR.
 *
 * Preconditions: columns and values are SQL fragments, normally a column
 * name and a placeholder from createNamedParameter(); nothing is escaped
 * or bound here.
 *
 * Thread-safety: stateless.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $expr = new ExpressionBuilder();
 *     $qb->where((string) $expr->and(
 *         $expr->eq('status', $qb->createNamedParameter('active')),
 *         $expr->gte('age', $qb->createNamedParameter(18, ParameterType::INTEGER->value)),
 *     ));
 *
 * References: ADR-009; SPEC-001 §5.
 *
 * @see https://www.php.net/manual/en/pdo.prepared-statements.php
 */
final class ExpressionBuilder
{
    public function and(string ...$parts): CompositeExpression
    {
        return CompositeExpression::and(...$parts);
    }

    public function or(string ...$parts): CompositeExpression
    {
        return CompositeExpression::or(...$parts);
    }

    public function eq(string $column, string $value): string
    {
        return "{$column} = {$value}";
    }

    public function neq(string $column, string $value): string
    {
        return "{$column} <> {$value}";
    }

    public function lt(string $column, string $value): string
    {
        return "{$column} < {$value}";
    }

    public function lte(string $column, string $value): string
    {
        return "{$column} <= {$value}";
    }

    public function gt(string $column, string $value): string
    {
        return "{$column} > {$value}";
    }

    public function gte(string $column, string $value): string
    {
        return "{$column} >= {$value}";
    }

    public function isNull(string $column): string
    {
        return "{$column} IS NULL";
    }

    public function isNotNull(string $column): string
    {
        return "{$column} IS NOT NULL";
    }

    public function like(string $column, string $value): string
    {
        return "{$column} LIKE {$value}";
    }

    public function notLike(string $column, string $value): string
    {
        return "{$column} NOT LIKE {$value}";
    }

    public function in(string $column, string $value): string
    {
        return "{$column} IN ({$value})";
    }

    public function notIn(string $column, string $value): string
    {
        return "{$column} NOT IN ({$value})";
    }

    public function between(string $column, string $min, string $max): string
    {
        return "{$column} BETWEEN {$min} AND {$max}";
    }
}
