<?php

declare(strict_types=1);

namespace App\Domain\DTO\Abstracts;

use BackedEnum;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JsonSerializable;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Базовый DTO: заполняется массивом по публичным свойствам наследника.
 *
 * Приведение значений:
 *  - явный каст из $casts (class-string, backed enum или callable), применяется к скаляру,
 *    к каждому элементу массива и коллекции;
 *  - иначе по типу свойства: backed enum через from(), класс через new $type($value);
 *  - сеттер set{Name}Attribute($value), если объявлен, вызывается до приведения.
 *
 * Неинициализированные свойства отличаются от явно переданного null, см. initialized().
 *
 * @implements Arrayable<string, mixed>
 */
abstract class DTO implements Arrayable, Jsonable, JsonSerializable
{
    /** @var array<string, class-string|callable> */
    protected array $casts = [];

    /** @var array<class-string, array<string, ReflectionProperty>> */
    private static array $propertiesCache = [];

    /**
     * @param  array<string, mixed>  $fields
     */
    public function __construct(array $fields = [])
    {
        $this->fill($fields);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function from(array $fields): static
    {
        /** @phpstan-ignore new.static */
        return new static($fields);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function fill(array $fields): static
    {
        foreach ($this->getProperties() as $name => $property) {
            if (! array_key_exists($name, $fields)) {
                continue;
            }

            $this->{$name} = $this->castValue($property, $this->checkSetterValue($name, $fields[$name]));
        }

        return $this;
    }

    /**
     * Копия DTO с изменёнными полями.
     *
     * @param  array<string, mixed>  $fields
     */
    public function with(array $fields): static
    {
        return (clone $this)->fill($fields);
    }

    /**
     * Все публичные свойства, неинициализированные будут null.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = [];

        foreach (array_keys($this->getProperties()) as $name) {
            $array[$name] = $this->normalize($this->{$name} ?? null, true);
        }

        return $array;
    }

    /**
     * Только инициализированные свойства (переданные в fill или имеющие значение по умолчанию).
     *
     * @return array<string, mixed>
     */
    public function initialized(bool $recursive = false): array
    {
        $array = [];

        foreach ($this->getProperties() as $name => $property) {
            if (! $property->isInitialized($this)) {
                continue;
            }

            $value = $this->{$name};

            $array[$name] = match (true) {
                $value instanceof self => $value->initialized($recursive),
                is_array($value) && ! $recursive => $value,
                default => $this->normalize($value, $recursive, initializedOnly: true),
            };
        }

        return $array;
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public function only(array $keys, bool $initialized = true): array
    {
        $values = $initialized ? $this->initialized() : $this->toArray();

        return array_intersect_key($values, array_flip($keys));
    }

    public function isInitialized(string $name): bool
    {
        $property = $this->getProperties()[$name] ?? null;

        return $property !== null && $property->isInitialized($this);
    }

    /**
     * @param  int  $options
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->toJson(JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * @return array<string, ReflectionProperty>
     */
    protected function getProperties(): array
    {
        if (! isset(self::$propertiesCache[static::class])) {
            $properties = [];

            foreach ((new \ReflectionClass(static::class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if (! $property->isStatic()) {
                    $properties[$property->getName()] = $property;
                }
            }

            self::$propertiesCache[static::class] = $properties;
        }

        return self::$propertiesCache[static::class];
    }

    protected function checkSetterValue(string $name, mixed $value): mixed
    {
        $method = 'set'.Str::studly($name).'Attribute';

        return method_exists($this, $method) ? $this->{$method}($value) : $value;
    }

    protected function castValue(ReflectionProperty $property, mixed $value): mixed
    {
        $name = $property->getName();

        if (isset($this->casts[$name])) {
            return $value === null ? null : $this->prepareCastValue($this->casts[$name], $value);
        }

        $type = $property->getType();

        if ($value === null || ! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return $value;
        }

        return $this->castToType($type->getName(), $value);
    }

    /**
     * @param  class-string|callable  $cast
     */
    protected function prepareCastValue(string|array|callable $cast, mixed $value): mixed
    {
        $castItem = fn (mixed $item): mixed => is_callable($cast)
            ? $cast($item)
            : $this->castToType($cast, $item);

        if (is_array($value)) {
            return array_map($castItem, $value);
        }

        if ($value instanceof Collection) {
            return $value->map($castItem);
        }

        return $castItem($value);
    }

    protected function castToType(string $type, mixed $value): mixed
    {
        if ($value === null || $value instanceof $type) {
            return $value;
        }

        if (is_subclass_of($type, BackedEnum::class)) {
            return $type::from($value);
        }

        if (class_exists($type) && ! (new \ReflectionClass($type))->isAbstract()) {
            return new $type($value);
        }

        return $value;
    }

    private function normalize(mixed $value, bool $recursive, bool $initializedOnly = false): mixed
    {
        return match (true) {
            $value instanceof self => $initializedOnly ? $value->initialized($recursive) : $value->toArray(),
            $value instanceof Arrayable => $value->toArray(),
            $value instanceof BackedEnum => $value->value,
            is_array($value) && $recursive => array_map(
                fn (mixed $item): mixed => $this->normalize($item, $recursive, $initializedOnly),
                $value,
            ),
            default => $value,
        };
    }
}
