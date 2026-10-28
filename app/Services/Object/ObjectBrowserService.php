<?php

declare(strict_types=1);

namespace App\Services\Object;

use App\Domain\DTO\Bucket\BucketAclData;
use App\Domain\DTO\Bucket\BucketSectionData;
use App\Domain\DTO\Bucket\BucketTagData;
use App\Domain\DTO\Object\MultipartUploadData;
use App\Domain\DTO\Object\ObjectDetailsData;
use App\Domain\DTO\Object\ObjectItemData;
use App\Domain\DTO\Object\ObjectListData;
use App\Domain\DTO\Object\ObjectProtectionData;
use App\Domain\DTO\Object\ObjectReferenceData;
use App\Domain\DTO\Object\ObjectVersionData;
use App\Domain\Enum\Audit\AuditAction;
use App\Domain\Enum\Bucket\CannedAcl;
use App\Domain\Enum\Bucket\RetentionMode;
use App\Domain\Enum\Storage\StorageCapability;
use App\Exceptions\DomainException;
use App\Services\Audit\AuditDetails;
use App\Services\Bucket\BucketAuditor;
use App\Services\Storage\StorageErrorTranslator;
use App\Services\Storage\StorageGateway;
use App\Services\Storage\StorageGatewayFactory;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Throwable;

/**
 * Просмотр объектов: содержимое «папок», свойства, версии, защита, ссылки на скачивание.
 */
final readonly class ObjectBrowserService
{
    public const int PAGE_SIZE = 200;

    /** Ссылка для скачивания из интерфейса: короткая, ей сразу пользуются */
    public const int DOWNLOAD_LINK_SECONDS = 300;

    /** Максимальный срок временной ссылки в S3 (Signature V4) — 7 суток */
    public const int MAX_LINK_SECONDS = 604800;

    public function __construct(
        private StorageGatewayFactory $gateways,
        private StorageErrorTranslator $errors,
        private BucketAuditor $auditor,
    ) {}

    /**
     * @param  string  $search  начало имени внутри текущей «папки»
     */
    public function list(int $storageId, string $bucket, string $prefix, string $search = '', ?string $token = null): ObjectListData
    {
        try {
            $page = $this->gateways->forStorage($storageId)->listFolder($bucket, $prefix.$search, $token, self::PAGE_SIZE);
        } catch (DomainException $e) {
            return new ObjectListData(['prefix' => $prefix, 'error' => $e->getMessage()]);
        } catch (Throwable $e) {
            return new ObjectListData(['prefix' => $prefix, 'error' => $this->errors->message($e)]);
        }

        return new ObjectListData([
            'prefix' => $prefix,
            'folders' => $page['folders'],
            'objects' => array_map(fn (array $object) => new ObjectItemData([
                ...$object,
                'name' => substr($object['key'], strlen($prefix)),
            ]), $page['objects']),
            'next' => $page['next'],
        ]);
    }

    public function details(int $storageId, string $bucket, ObjectReferenceData $object): ObjectDetailsData
    {
        return $this->read($storageId, function (StorageGateway $gateway) use ($bucket, $object): ObjectDetailsData {
            $head = $gateway->headObject($bucket, $object->key, $object->versionId);

            $tags = null;

            if ($gateway->supports(StorageCapability::Tagging)) {
                try {
                    $tags = array_map(fn (array $tag) => new BucketTagData($tag), $gateway->getObjectTags($bucket, $object->key, $object->versionId));
                } catch (Throwable) {
                    // Нет прав на теги или хранилище их не отдаёт — показываем остальное
                    $tags = null;
                }
            }

            $modified = $head['LastModified'] ?? null;

            return new ObjectDetailsData([
                'key' => $object->key,
                'size' => (int) ($head['ContentLength'] ?? 0),
                'contentType' => $head['ContentType'] ?? null,
                'cacheControl' => $head['CacheControl'] ?? null,
                'contentDisposition' => $head['ContentDisposition'] ?? null,
                'contentEncoding' => $head['ContentEncoding'] ?? null,
                'etag' => isset($head['ETag']) ? trim((string) $head['ETag'], '"') : null,
                'lastModified' => $modified instanceof DateTimeInterface ? CarbonImmutable::instance($modified) : null,
                'storageClass' => $head['StorageClass'] ?? null,
                'versionId' => isset($head['VersionId']) && $head['VersionId'] !== 'null' ? (string) $head['VersionId'] : null,
                'metadata' => array_map(strval(...), (array) ($head['Metadata'] ?? [])),
                'tags' => $tags,
            ]);
        });
    }

    /**
     * @return list<ObjectVersionData>
     */
    public function versions(int $storageId, string $bucket, string $key): array
    {
        return $this->read($storageId, fn (StorageGateway $gateway) => array_map(
            fn (array $version) => new ObjectVersionData($version),
            $gateway->objectVersions($bucket, $key),
        ));
    }

    public function acl(int $storageId, string $bucket, string $key): BucketSectionData
    {
        return $this->section($storageId, function (StorageGateway $gateway) use ($bucket, $key): BucketAclData {
            $acl = $gateway->getObjectAcl($bucket, $key);

            return new BucketAclData([...$acl, 'canned' => CannedAcl::detect($acl['grants'])]);
        });
    }

    public function protection(int $storageId, string $bucket, ObjectReferenceData $object): BucketSectionData
    {
        return $this->section($storageId, function (StorageGateway $gateway) use ($bucket, $object): ObjectProtectionData {
            $protection = $gateway->getObjectProtection($bucket, $object->key, $object->versionId);

            return new ObjectProtectionData([
                'mode' => $protection['mode'] === null ? null : RetentionMode::tryFrom($protection['mode']),
                'until' => $protection['until'],
                'legalHold' => $protection['legalHold'],
            ]);
        });
    }

    /**
     * Короткая ссылка для скачивания из интерфейса. В журнал не пишется: это обычный просмотр.
     */
    public function downloadUrl(int $storageId, string $bucket, ObjectReferenceData $object): string
    {
        return $this->read($storageId, fn (StorageGateway $gateway) => $gateway->presignGet(
            $bucket,
            $object->key,
            $object->versionId,
            self::DOWNLOAD_LINK_SECONDS,
            basename($object->key),
        ));
    }

    /**
     * Временная публичная ссылка, которой можно поделиться. Записывается в журнал: по ней доступ получает кто угодно.
     */
    public function shareLink(int $storageId, string $bucket, ObjectReferenceData $object, int $seconds, bool $asAttachment): string
    {
        $seconds = max(60, min($seconds, self::MAX_LINK_SECONDS));

        $url = $this->read($storageId, fn (StorageGateway $gateway) => $gateway->presignGet(
            $bucket,
            $object->key,
            $object->versionId,
            $seconds,
            $asAttachment ? basename($object->key) : null,
        ));

        $this->auditor->record(AuditAction::ObjectLinkCreated, $storageId, $bucket, [
            ...AuditDetails::objects([$this->label($object)]),
            ...AuditDetails::values(['expires_at' => now()->utc()->addSeconds($seconds)->toDateTimeString().' UTC']),
        ]);

        return $url;
    }

    /**
     * @return list<MultipartUploadData>
     */
    public function uploads(int $storageId, string $bucket): array
    {
        return $this->read($storageId, fn (StorageGateway $gateway) => array_map(
            fn (array $upload) => new MultipartUploadData($upload),
            $gateway->listUploads($bucket, 1000),
        ));
    }

    private function label(ObjectReferenceData $object): string
    {
        return $object->versionId === null ? $object->key : "{$object->key} ({$object->versionId})";
    }

    /**
     * @template T
     *
     * @param  Closure(StorageGateway): T  $read
     * @return T
     */
    private function read(int $storageId, Closure $read): mixed
    {
        try {
            return $read($this->gateways->forStorage($storageId));
        } catch (DomainException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new DomainException($this->errors->message($e), previous: $e);
        }
    }

    /**
     * @param  Closure(StorageGateway): mixed  $read
     */
    private function section(int $storageId, Closure $read): BucketSectionData
    {
        try {
            return new BucketSectionData(['data' => $read($this->gateways->forStorage($storageId))]);
        } catch (DomainException $e) {
            return new BucketSectionData(['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            return new BucketSectionData(['error' => $this->errors->message($e)]);
        }
    }
}
