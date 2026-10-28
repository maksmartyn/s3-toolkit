<?php

declare(strict_types=1);

namespace App\Domain\DTO\Bucket;

use App\Domain\DTO\Abstracts\DTO;
use App\Domain\DTO\Operation\OperationData;
use Carbon\CarbonImmutable;

final class BucketData extends DTO
{
    public string $name;

    /** null — бакет задан вручную в настройках хранилища, дата неизвестна */
    public ?CarbonImmutable $createdAt = null;

    public ?BucketStatsData $stats = null;

    /** @var list<OperationData> активные фоновые операции над бакетом */
    public array $operations = [];
}
