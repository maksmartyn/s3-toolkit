<?php

declare(strict_types=1);

namespace App\Domain\DTO\Bucket;

use App\Domain\DTO\Abstracts\DTO;
use Carbon\CarbonImmutable;

final class BucketStatsData extends DTO
{
    /** Текущие объекты (последние версии, без маркеров удаления) */
    public int $objectCount;

    public int $totalSize;

    /** Все версии, включая маркеры удаления; null — версионирование не учитывалось */
    public ?int $versionCount = null;

    public ?int $versionsSize = null;

    public CarbonImmutable $calculatedAt;
}
