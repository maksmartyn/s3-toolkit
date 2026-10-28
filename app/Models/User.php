<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\PermissionMaskCast;
use App\Domain\Enum\Permission\SystemPermission;
use App\Domain\ValueObject\Permission\PermissionMask;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $active_email
 * @property string $password
 * @property bool $password_change_required
 * @property bool $is_superadmin
 * @property bool $is_active
 * @property PermissionMask<SystemPermission> $permissions
 * @property CarbonInterface|null $last_login_at
 * @property string|null $remember_token
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 */
#[Fillable(['name', 'email', 'password', 'password_change_required', 'is_active', 'permissions', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'password_change_required' => 'boolean',
            'is_superadmin' => 'boolean',
            'is_active' => 'boolean',
            'permissions' => PermissionMaskCast::class.':'.SystemPermission::class,
            'last_login_at' => 'datetime',
        ];
    }
}
