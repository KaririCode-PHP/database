<?php

declare(strict_types=1);

namespace KaririCode\Database\Type;

use KaririCode\Database\Exception\ConfigurationException;
use KaririCode\Database\Contract\TypeConverter;
use KaririCode\Database\Exception\TypeConversionException;

/**
 * Looks up the converter for a type name: the 18 built-ins (integer,
 * bigint, smallint, float, decimal, string, ascii_string, text, boolean,
 * datetime, datetimetz, date, time, dateinterval, json, uuid, binary,
 * blob) plus any the application registers.
 *
 * Preconditions: an unknown name is ConfigurationException 6121;
 * register() replaces a converter of the same name, built-ins included.
 *
 * Thread-safety: register() mutates the registry; set it up during boot
 * and share it read-only afterwards.
 *
 * Side effects: none.
 *
 * Example:
 *
 *     $registry = new TypeRegistry();
 *     $registry->register('money', new MoneyType());
 *     $amount = $registry->get('money')->convertToPhpValue($row['amount'], $connection->platform);
 *
 * References: ADR-007 (extensible type registry); SPEC-001 §2.4.
 *
 * @see https://www.doctrine-project.org/projects/doctrine-dbal/en/current/reference/types.html
 */
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
