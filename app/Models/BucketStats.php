<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $storage_id
 * @property string $bucket
 * @property int $object_count
 * @property int $total_size
 * @property int|null $version_count
 * @property int|null $versions_size
 * @property CarbonInterface $calculated_at
 */
#[Fillable(['storage_id', 'bucket', 'object_count', 'total_size', 'version_count', 'versions_size', 'calculated_at'])]
class BucketStats extends Model
{
    public $timestamps = false;

    protected $table = 'bucket_stats';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'object_count' => 'integer',
            'total_size' => 'integer',
            'version_count' => 'integer',
            'versions_size' => 'integer',
            'calculated_at' => 'datetime',
        ];
    }
}
