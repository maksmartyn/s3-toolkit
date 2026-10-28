<?php

declare(strict_types=1);

namespace App\Repositories\Operation;

use App\Domain\Enum\Operation\OperationType;
use App\Models\BackgroundOperation;
use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<BackgroundOperation>
 */
interface OperationRepositoryInterface extends RepositoryInterface
{
    /**
     * @return Collection<int, BackgroundOperation>
     */
    public function activeFor(int $storageId): Collection;

    public function activeForBucket(int $storageId, string $bucket, ?OperationType $type = null): ?BackgroundOperation;

    /**
     * Последние завершённые операции хранилища, в том числе неудачные.
     *
     * @return Collection<int, BackgroundOperation>
     */
    public function recentFinished(int $storageId, int $limit): Collection;
}
