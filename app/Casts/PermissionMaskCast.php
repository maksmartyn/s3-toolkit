<?php

declare(strict_types=1);

namespace App\Casts;

use App\Domain\Enum\Permission\Contracts\BitPermission;
use App\Domain\ValueObject\Permission\PermissionMask;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Использование: 'permissions' => PermissionMaskCast::class.':'.SystemPermission::class
 *
 * @implements CastsAttributes<PermissionMask<BitPermission>, PermissionMask<BitPermission>|int>
 */
final readonly class PermissionMaskCast implements CastsAttributes
{
    /**
     * @param  class-string<BitPermission>  $enum
     */
    public function __construct(private string $enum)
    {
        if (! is_subclass_of($enum, BitPermission::class)) {
            throw new InvalidArgumentException("{$enum} must implement ".BitPermission::class.'.');
        }
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): PermissionMask
    {
        return PermissionMask::fromInt($this->enum, (int) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): int
    {
        if ($value instanceof PermissionMask) {
            if ($value->enum !== $this->enum) {
                throw new InvalidArgumentException("Expected {$this->enum} mask, got {$value->enum}.");
            }

            return $value->value;
        }

        return PermissionMask::fromInt($this->enum, (int) $value)->value;
    }
}
