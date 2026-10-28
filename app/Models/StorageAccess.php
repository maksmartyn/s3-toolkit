<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\PermissionMaskCast;
use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\ValueObject\Permission\PermissionMask;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Права пользователя на хранилище (storage_user). Ключ составной, поэтому запись меняется через репозиторий запросами.
 *
 * @property int $storage_id
 * @property int $user_id
 * @property PermissionMask<StoragePermission> $permissions
 * @property int|null $granted_by
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read User $user
 * @property-read Storage $storage
 */
#[Fillable(['storage_id', 'user_id', 'permissions', 'granted_by'])]
class StorageAccess extends Model
{
    public $incrementing = false;

    protected $table = 'storage_user';

    protected $primaryKey = 'storage_id';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Storage, $this>
     */
    public function storage(): BelongsTo
    {
        return $this->belongsTo(Storage::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissions' => PermissionMaskCast::class.':'.StoragePermission::class,
        ];
    }
}
