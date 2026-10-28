<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Enum\Operation\OperationStatus;
use App\Domain\Enum\Operation\OperationType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int $storage_id
 * @property OperationType $type
 * @property string $bucket
 * @property OperationStatus $status
 * @property int $processed
 * @property int|null $total
 * @property array<string, mixed>|null $state
 * @property array<string, mixed>|null $result
 * @property string|null $error
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $finished_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['user_id', 'storage_id', 'type', 'bucket', 'status', 'processed', 'total', 'state', 'result', 'error', 'started_at', 'finished_at'])]
class BackgroundOperation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OperationType::class,
            'status' => OperationStatus::class,
            'processed' => 'integer',
            'total' => 'integer',
            'state' => 'array',
            'result' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
