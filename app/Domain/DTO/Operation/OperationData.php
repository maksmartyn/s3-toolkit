<?php

declare(strict_types=1);

namespace App\Domain\DTO\Operation;

use App\Domain\DTO\Abstracts\DTO;
use App\Domain\Enum\Operation\OperationStatus;
use App\Domain\Enum\Operation\OperationType;
use Carbon\CarbonImmutable;

final class OperationData extends DTO
{
    public int $id;

    public int $storageId;

    public OperationType $type;

    public string $typeLabel;

    public string $bucket;

    /** Для операций над «папкой» — её префикс */
    public ?string $prefix = null;

    public OperationStatus $status;

    public string $statusLabel;

    public int $processed;

    public ?int $total;

    public ?string $error;

    public ?int $userId;

    public ?CarbonImmutable $startedAt;

    public ?CarbonImmutable $finishedAt;

    public ?CarbonImmutable $createdAt;
}
