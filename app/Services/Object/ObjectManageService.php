<?php

declare(strict_types=1);

namespace App\Services\Object;

use App\Domain\DTO\Bucket\BucketTagData;
use App\Domain\DTO\Object\ObjectMetadataData;
use App\Domain\DTO\Object\ObjectReferenceData;
use App\Domain\DTO\Operation\OperationData;
use App\Domain\Enum\Audit\AuditAction;
use App\Domain\Enum\Bucket\CannedAcl;
use App\Domain\Enum\Bucket\RetentionMode;
use App\Domain\Enum\Operation\OperationType;
use App\Exceptions\DomainException;
use App\Services\Audit\AuditDetails;
use App\Services\Bucket\BucketAuditor;
use App\Services\Operation\OperationService;
use App\Services\Storage\StorageErrorTranslator;
use App\Services\Storage\StorageGateway;
use App\Services\Storage\StorageGatewayFactory;
use Closure;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Изменение объектов: удаление, папки, копирование и перемещение, метаданные, теги, ACL, защита, версии.
 * Операции над «папками» целиком выполняются в фоне (FolderOperationRunner).
 */
final readonly class ObjectManageService
{
    /** Сколько объектов можно скопировать или переместить одним запросом; папки — в фоне */
    public const int MAX_SYNC_OBJECTS = 100;

    public function __construct(
        private StorageGatewayFactory $gateways,
        private StorageErrorTranslator $errors,
        private BucketAuditor $auditor,
        private OperationService $operations,
    ) {}

    /**
     * Удаление объектов или конкретных версий (до 1000 за раз). Без версии в версионированном бакете
     * создаётся маркер удаления — объект можно восстановить.
     *
     * @param  list<ObjectReferenceData>  $objects
     */
    public function delete(int $storageId, string $bucket, array $objects): void
    {
        if ($objects === []) {
            return;
        }

        $failed = $this->call($storageId, fn (StorageGateway $gateway) => $gateway->deleteObjects($bucket, array_map(
            fn (ObjectReferenceData $object) => ['key' => $object->key, 'versionId' => $object->versionId],
            $objects,
        )));

        $deleted = array_values(array_filter(
            $objects,
            fn (ObjectReferenceData $object) => ! in_array($object->key, array_column($failed, 'key'), true),
        ));

        if ($deleted !== []) {
            $this->auditor->record(AuditAction::ObjectsDeleted, $storageId, $bucket, AuditDetails::objects(array_map($this->label(...), $deleted)));
        }

        if ($failed !== []) {
            throw new DomainException(__('Could not delete :count objects, for example :key: :error', [
                'count' => count($failed),
                'key' => $failed[0]['key'],
                'error' => trim($failed[0]['code'].' '.$failed[0]['message']),
            ]));
        }
    }

    /**
     * «Папка» в S3 — пустой объект с ключом, оканчивающимся на «/».
     */
    public function createFolder(int $storageId, string $bucket, string $prefix): void
    {
        $this->call($storageId, function (StorageGateway $gateway) use ($bucket, $prefix): void {
            $body = fopen('php://memory', 'rb');

            if ($body === false) {
                throw new DomainException(__('Could not create the folder.'));
            }

            try {
                $gateway->putObject($bucket, rtrim($prefix, '/').'/', $body, 'application/x-directory');
            } finally {
                fclose($body);
            }
        });
    }

    /**
     * Копирование или перемещение объектов в «папку» (возможно, другого бакета этого хранилища).
     * Имя внутри папки сохраняется: ключ без текущего префикса добавляется к целевому.
     *
     * @param  list<string>  $keys
     */
    public function transfer(int $storageId, string $bucket, array $keys, string $sourcePrefix, string $targetBucket, string $targetPrefix, bool $move): void
    {
        if (count($keys) > self::MAX_SYNC_OBJECTS) {
            throw new DomainException(__('Too many objects at once: at most :count.', ['count' => self::MAX_SYNC_OBJECTS]));
        }

        $pairs = [];

        foreach ($keys as $key) {
            $target = $targetPrefix.(str_starts_with($key, $sourcePrefix) ? substr($key, strlen($sourcePrefix)) : basename($key));

            if ($targetBucket === $bucket && $target === $key) {
                continue;
            }

            $pairs[$key] = $target;
        }

        $this->copyAll($storageId, $bucket, $targetBucket, $pairs, $move);
    }

    /**
     * Переименование (перемещение под другим именем) одного объекта.
     */
    public function rename(int $storageId, string $bucket, string $key, string $newKey): void
    {
        if ($key !== $newKey) {
            $this->copyAll($storageId, $bucket, $bucket, [$key => $newKey], true);
        }
    }

    /**
     * Фоновое удаление, копирование или перемещение «папки» целиком.
     */
    public function startFolderOperation(int $actorId, int $storageId, string $bucket, OperationType $type, string $prefix, ?string $targetBucket = null, ?string $targetPrefix = null): OperationData
    {
        $prefix = rtrim($prefix, '/').'/';

        if ($type !== OperationType::FolderDelete) {
            $targetPrefix = $targetPrefix === null || $targetPrefix === '' ? '' : rtrim($targetPrefix, '/').'/';
            $targetPrefix .= basename($prefix).'/';

            // Копия внутрь самой себя никогда не закончится
            if ($targetBucket === $bucket && str_starts_with($targetPrefix, $prefix)) {
                throw new DomainException(__('A folder cannot be copied or moved into itself.'));
            }
        }

        return DB::transaction(function () use ($actorId, $storageId, $bucket, $type, $prefix, $targetBucket, $targetPrefix): OperationData {
            $operation = $this->operations->start($actorId, $storageId, $type, $bucket, params: array_filter([
                'prefix' => $prefix,
                'targetBucket' => $targetBucket,
                'targetPrefix' => $targetPrefix,
            ], fn (mixed $value) => $value !== null));

            $action = match ($type) {
                OperationType::FolderDelete => AuditAction::FolderDeletionStarted,
                OperationType::FolderMove => AuditAction::FolderMoveStarted,
                default => null,
            };

            if ($action !== null) {
                $this->auditor->record($action, $storageId, $bucket, AuditDetails::values(array_filter([
                    'prefix' => $prefix,
                    'target' => $targetBucket === null ? null : $targetBucket.'/'.$targetPrefix,
                ])));
            }

            return $operation;
        });
    }

    public function updateMetadata(int $storageId, string $bucket, string $key, ObjectMetadataData $data): void
    {
        $this->call($storageId, fn (StorageGateway $gateway) => $gateway->replaceMetadata(
            $bucket,
            $key,
            $data->contentType,
            $data->cacheControl,
            $data->contentDisposition,
            $data->metadata,
        ));
    }

    /**
     * @param  list<BucketTagData>  $tags
     */
    public function updateTags(int $storageId, string $bucket, string $key, array $tags): void
    {
        $this->call($storageId, fn (StorageGateway $gateway) => $gateway->putObjectTags(
            $bucket,
            $key,
            array_map(fn (BucketTagData $tag) => ['key' => $tag->key, 'value' => $tag->value], $tags),
        ));
    }

    public function setAcl(int $storageId, string $bucket, string $key, CannedAcl $acl): void
    {
        $this->call($storageId, fn (StorageGateway $gateway) => $gateway->putObjectCannedAcl($bucket, $key, $acl->value));

        $this->auditor->record(AuditAction::ObjectAclChanged, $storageId, $bucket, [
            ...AuditDetails::objects([$key]),
            ...AuditDetails::values(['acl' => $acl->label()]),
        ]);
    }

    public function setRetention(int $storageId, string $bucket, ObjectReferenceData $object, RetentionMode $mode, DateTimeInterface $until): void
    {
        $this->call($storageId, fn (StorageGateway $gateway) => $gateway->putObjectRetention($bucket, $object->key, $object->versionId, $mode->value, $until));

        $this->auditor->record(AuditAction::ObjectRetentionChanged, $storageId, $bucket, [
            ...AuditDetails::objects([$this->label($object)]),
            ...AuditDetails::values(['retention' => $mode->label().', '.__('until :date', ['date' => $until->format('Y-m-d H:i')])]),
        ]);
    }

    public function setLegalHold(int $storageId, string $bucket, ObjectReferenceData $object, bool $on): void
    {
        $this->call($storageId, fn (StorageGateway $gateway) => $gateway->putObjectLegalHold($bucket, $object->key, $object->versionId, $on));

        $this->auditor->record(AuditAction::ObjectLegalHoldChanged, $storageId, $bucket, [
            ...AuditDetails::objects([$this->label($object)]),
            ...AuditDetails::values(['legal_hold' => $on ? __('On') : __('Off')]),
        ]);
    }

    /**
     * Восстановление старой версии: её копия становится текущей версией. Остальные версии не меняются.
     */
    public function restoreVersion(int $storageId, string $bucket, string $key, string $versionId): void
    {
        $this->call($storageId, fn (StorageGateway $gateway) => $gateway->copyObject($bucket, $key, $versionId, $bucket, $key));

        $this->auditor->record(AuditAction::ObjectVersionRestored, $storageId, $bucket, [
            ...AuditDetails::objects([$key]),
            ...AuditDetails::values(['version' => $versionId]),
        ]);
    }

    /**
     * @param  list<array{key: string, uploadId: string}>  $uploads
     */
    public function abortUploads(int $storageId, string $bucket, array $uploads): void
    {
        $this->call($storageId, function (StorageGateway $gateway) use ($bucket, $uploads): void {
            foreach ($uploads as $upload) {
                $gateway->abortUpload($bucket, $upload['key'], $upload['uploadId']);
            }
        });

        $this->auditor->record(AuditAction::UploadsAborted, $storageId, $bucket, AuditDetails::objects(array_column($uploads, 'key')));
    }

    /**
     * @param  array<string, string>  $pairs  исходный ключ => целевой ключ
     */
    private function copyAll(int $storageId, string $bucket, string $targetBucket, array $pairs, bool $move): void
    {
        if ($pairs === []) {
            return;
        }

        $this->call($storageId, function (StorageGateway $gateway) use ($bucket, $targetBucket, $pairs, $move): void {
            foreach ($pairs as $source => $target) {
                $gateway->copyObject($bucket, $source, null, $targetBucket, $target);
            }

            if ($move) {
                $failed = $gateway->deleteObjects($bucket, array_map(fn (string $key) => ['key' => $key], array_keys($pairs)));

                if ($failed !== []) {
                    throw new DomainException(__('Objects were copied, but :count originals could not be deleted, for example :key.', [
                        'count' => count($failed),
                        'key' => $failed[0]['key'],
                    ]));
                }
            }
        });

        if ($move) {
            $this->auditor->record(AuditAction::ObjectsMoved, $storageId, $bucket, [
                ...AuditDetails::objects(array_map(fn (string $source, string $target) => "{$source} → {$targetBucket}/{$target}", array_keys($pairs), $pairs)),
            ]);
        }
    }

    private function label(ObjectReferenceData $object): string
    {
        return $object->versionId === null ? $object->key : "{$object->key} ({$object->versionId})";
    }

    /**
     * @template T
     *
     * @param  Closure(StorageGateway): T  $call
     * @return T
     */
    private function call(int $storageId, Closure $call): mixed
    {
        try {
            return $call($this->gateways->forStorage($storageId));
        } catch (DomainException $e) {
            throw $e;
        } catch (Throwable $e) {
            report_if(config('app.debug'), $e);
            throw new DomainException(__('The storage rejected the change: :error', ['error' => $this->errors->message($e)]), previous: $e);
        }
    }
}
