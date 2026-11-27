<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Exception\ConfigurationException;
use KaririCode\Database\Contract\TypeConverter;
use KaririCode\Database\Exception\TypeConversionException;

// ── TypeRegistry ─────────────────────────────────────────────────────
//
// Flyweight registry of type converters. Ships with 13 built-in
// types. Custom types registered via register().
//
// Ref: Doctrine DBAL 4.4 — "Types are flyweights. There is only
// ever one instance of a type and it is not allowed to contain
// any state."
// ─────────────────────────────────────────────────────────────────────

final class TypeRegistry
{
    /** @var array<string, TypeConverter> */
    private array $types = [];

    public function __construct()
    {
        $this->registerBuiltins();
    }

    public function get(string $name): TypeConverter
    {
        if (!isset($this->types[$name])) {
            throw ConfigurationException::unknownType();
        }

        return $this->types[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->types[$name]);
    }

    public function register(string $name, TypeConverter $converter): void
    {
        $this->types[$name] = $converter;
    }

    /** @return array<string, TypeConverter> */
    public function all(): array
    {
        return $this->types;
    }

    private function registerBuiltins(): void
    {
        $this->types = [
            'integer' => new IntegerType(),
            'bigint' => new BigIntType(),
            'smallint' => new SmallIntType(),
            'float' => new FloatType(),
            'decimal' => new DecimalType(),
            'string' => new StringType(),
            'ascii_string' => new AsciiStringType(),
            'text' => new TextType(),
            'boolean' => new BooleanType(),
            'datetime' => new DateTimeType(),
            'datetimetz' => new DateTimeTzType(),
            'date' => new DateType(),
            'time' => new TimeType(),
            'dateinterval' => new DateIntervalType(),
            'json' => new JsonType(),
            'uuid' => new UuidType(),
            'binary' => new BinaryType(),
            'blob' => new BlobType(),
        ];
    }
}
