<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Enum\Storage\StorageProvider;
use Carbon\CarbonInterface;
use Database\Factories\StorageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Учётная запись S3-совместимого хранилища.
 *
 * @property int $id
 * @property string $name
 * @property StorageProvider $provider
 * @property string|null $endpoint
 * @property string $region
 * @property bool $use_path_style
 * @property bool $verify_ssl
 * @property string $access_key_id
 * @property string $secret_access_key
 * @property array<string, mixed>|null $admin_credentials
 * @property list<string>|null $buckets
 * @property array<string, bool>|null $capabilities
 * @property int|null $created_by
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'name', 'provider', 'endpoint', 'region', 'use_path_style', 'verify_ssl',
    'access_key_id', 'secret_access_key', 'admin_credentials', 'buckets', 'capabilities', 'created_by',
])]
#[Hidden(['access_key_id', 'secret_access_key', 'admin_credentials'])]
class Storage extends Model
{
    /** @use HasFactory<StorageFactory> */
    use HasFactory;

    /**
     * @return HasMany<StorageAccess, $this>
     */
    public function accesses(): HasMany
    {
        return $this->hasMany(StorageAccess::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => StorageProvider::class,
            'use_path_style' => 'boolean',
            'verify_ssl' => 'boolean',
            'access_key_id' => 'encrypted',
            'secret_access_key' => 'encrypted',
            'admin_credentials' => 'encrypted:array',
            'buckets' => 'array',
            'capabilities' => 'array',
        ];
    }
}
