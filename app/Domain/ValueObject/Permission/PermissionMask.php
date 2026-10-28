<?php

declare(strict_types=1);

namespace App\Domain\ValueObject\Permission;

use App\Domain\Enum\Permission\Contracts\BitPermission;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Неизменяемая битовая маска прав одного типа (базовые или права хранилища).
 *
 * @template TPermission of BitPermission
 */
final readonly class PermissionMask implements JsonSerializable
{
    public const int MAX_BIT = 62;

    /**
     * @param  class-string<TPermission>  $enum
     */
    private function __construct(
        public string $enum,
        public int $value,
    ) {
        if ($value < 0) {
            throw new InvalidArgumentException('Permission mask cannot be negative.');
        }
    }

    /**
     * @template T of BitPermission
     *
     * @param  class-string<T>  $enum
     * @return self<T>
     */
    public static function fromInt(string $enum, int $value): self
    {
        return new self($enum, $value);
    }

    /**
     * @template T of BitPermission
     *
     * @param  class-string<T>  $enum
     * @return self<T>
     */
    public static function none(string $enum): self
    {
        return new self($enum, 0);
    }

    /**
     * @template T of BitPermission
     *
     * @param  class-string<T>  $enum
     * @param  iterable<T>  $permissions
     * @return self<T>
     */
    public static function of(string $enum, iterable $permissions): self
    {
        $mask = self::none($enum);

        foreach ($permissions as $permission) {
            $mask = $mask->grant($permission);
        }

        return $mask;
    }

    /**
     * Маска из значений enum (например, из запроса).
     *
     * @template T of BitPermission
     *
     * @param  class-string<T>  $enum
     * @param  iterable<int>  $values
     * @return self<T>
     */
    public static function fromValues(string $enum, iterable $values): self
    {
        $permissions = [];

        foreach ($values as $value) {
            $permissions[] = $enum::from($value);
        }

        return self::of($enum, $permissions);
    }

    /**
     * Все права enum.
     *
     * @template T of BitPermission
     *
     * @param  class-string<T>  $enum
     * @return self<T>
     */
    public static function all(string $enum): self
    {
        return self::of($enum, $enum::cases());
    }

    /**
     * @param  TPermission  $permission
     */
    public function has(BitPermission $permission): bool
    {
        $bit = self::bitOf($this->assertType($permission));

        return ($this->value & $bit) === $bit;
    }

    /**
     * @param  TPermission  $permission
     * @return self<TPermission>
     */
    public function grant(BitPermission $permission): self
    {
        return new self($this->enum, $this->value | self::bitOf($this->assertType($permission)));
    }

    /**
     * @param  TPermission  $permission
     * @return self<TPermission>
     */
    public function revoke(BitPermission $permission): self
    {
        return new self($this->enum, $this->value & ~self::bitOf($this->assertType($permission)));
    }

    /**
     * @param  self<TPermission>  $other
     */
    public function isSubsetOf(self $other): bool
    {
        $this->assertSameEnum($other);

        return ($this->value & ~$other->value) === 0;
    }

    /**
     * @param  self<TPermission>  $other
     */
    public function equals(self $other): bool
    {
        return $this->enum === $other->enum && $this->value === $other->value;
    }

    public function isEmpty(): bool
    {
        return $this->value === 0;
    }

    /**
     * Известные права из маски. Неизвестные биты (например, от удалённых прав) игнорируются.
     *
     * @return list<TPermission>
     */
    public function permissions(): array
    {
        return array_values(array_filter(
            $this->enum::cases(),
            fn (BitPermission $permission): bool => $this->has($permission),
        ));
    }

    /**
     * @return list<int>
     */
    public function values(): array
    {
        return array_map(fn (BitPermission $permission): int => (int) $permission->value, $this->permissions());
    }

    /**
     * @return list<int>
     */
    public function jsonSerialize(): array
    {
        return $this->values();
    }

    private static function bitOf(BitPermission $permission): int
    {
        $bit = $permission->bit();

        if ($bit < 0 || $bit > self::MAX_BIT) {
            throw new InvalidArgumentException("Permission bit {$bit} is out of range 0..".self::MAX_BIT.'.');
        }

        return 1 << $bit;
    }

    /**
     * @return TPermission
     */
    private function assertType(BitPermission $permission): BitPermission
    {
        if (! $permission instanceof $this->enum) {
            throw new InvalidArgumentException(sprintf('Expected %s, got %s.', $this->enum, $permission::class));
        }

        return $permission;
    }

    /**
     * @param  self<TPermission>  $other
     */
    private function assertSameEnum(self $other): void
    {
        if ($other->enum !== $this->enum) {
            throw new InvalidArgumentException(sprintf('Cannot compare %s mask with %s mask.', $this->enum, $other->enum));
        }
    }
}
