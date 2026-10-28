<?php

declare(strict_types=1);

namespace App\Services\Bucket;

use App\Domain\DTO\Audit\AuditRecordData;
use App\Domain\Enum\Audit\AuditAction;
use App\Repositories\Storage\StorageRepositoryInterface;
use App\Services\Audit\AuditDetails;
use App\Services\Audit\AuditService;

/**
 * Запись действий над бакетом и его объектами: объект журнала — бакет, в подробностях — хранилище.
 */
final readonly class BucketAuditor
{
    public function __construct(
        private StorageRepositoryInterface $storages,
        private AuditService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $details
     */
    public function record(AuditAction $action, int $storageId, string $bucket, array $details = [], ?int $actorId = null): void
    {
        $storage = $this->storages->findOrFail($storageId);

        $this->audit->record(new AuditRecordData([
            'action' => $action,
            'subjectId' => $storageId,
            'subjectLabel' => $bucket,
            'actorId' => $actorId,
            'details' => [...AuditDetails::storage($storageId, $storage->name), ...$details],
        ]));
    }
}
