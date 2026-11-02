<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Domain\Enum\Storage\StorageCapability;
use App\Domain\Enum\Storage\StorageProvider;
use Aws\Exception\AwsException;
use Aws\S3\ObjectCopier;
use Aws\S3\S3Client;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;

/**
 * Обращения к S3 API одного хранилища. Скрывает SDK от сервисов и задач и возвращает простые массивы.
 *
 * Не кешируется между запросами и задачами (см. S3ClientFactory).
 */
final readonly class StorageGateway
{
    public const int PAGE_SIZE = 1000;

    /**
     * @param  Closure(): S3Client  $presigner  клиент для подписи ссылок (с публичным endpoint)
     * @param  list<StorageCapability>  $capabilities
     * @param  list<string>|null  $fixedBuckets
     */
    public function __construct(
        private S3Client $client,
        private Closure $presigner,
        public StorageProvider $provider,
        public string $region,
        public array $capabilities,
        public ?array $fixedBuckets,
    ) {}

    public function supports(StorageCapability $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    /**
     * @return list<array{name: string, createdAt: CarbonImmutable|null}>
     */
    public function listBuckets(): array
    {
        $buckets = [];

        foreach ((array) ($this->client->listBuckets()->get('Buckets') ?? []) as $bucket) {
            $created = $bucket['CreationDate'] ?? null;

            $buckets[] = [
                'name' => (string) $bucket['Name'],
                'createdAt' => $created instanceof DateTimeInterface ? CarbonImmutable::instance($created) : null,
            ];
        }

        return $buckets;
    }

    public function createBucket(string $name, bool $objectLock): void
    {
        $params = ['Bucket' => $name];

        // AWS требует LocationConstraint для всех регионов, кроме us-east-1; остальные провайдеры берут регион из endpoint
        if ($this->provider === StorageProvider::Aws && $this->region !== 'us-east-1') {
            $params['CreateBucketConfiguration'] = ['LocationConstraint' => $this->region];
        }

        if ($objectLock) {
            $params['ObjectLockEnabledForBucket'] = true;
        }

        // Через команду: в типах SDK регион — перечисление регионов AWS, а у других провайдеров регионы свои
        $this->client->execute($this->client->getCommand('CreateBucket', $params));
    }

    public function deleteBucket(string $name): void
    {
        $this->client->deleteBucket(['Bucket' => $name]);
    }

    /**
     * Страница текущих объектов.
     *
     * @return array{items: list<array{key: string, size: int}>, next: string|null}
     */
    public function listObjectsPage(string $bucket, ?string $token): array
    {
        $result = $this->client->listObjectsV2(array_filter([
            'Bucket' => $bucket,
            'MaxKeys' => self::PAGE_SIZE,
            'ContinuationToken' => $token,
        ]));

        $items = array_map(
            fn (array $object) => ['key' => (string) $object['Key'], 'size' => (int) ($object['Size'] ?? 0)],
            (array) ($result->get('Contents') ?? []),
        );

        return [
            'items' => array_values($items),
            'next' => $result->get('IsTruncated') ? ($result->get('NextContinuationToken') ?: null) : null,
        ];
    }

    /**
     * Страница всех версий и маркеров удаления.
     *
     * @return array{
     *     items: list<array{key: string, versionId: string|null, size: int, isLatest: bool, isDeleteMarker: bool}>,
     *     nextKey: string|null,
     *     nextVersion: string|null
     * }
     */
    public function listVersionsPage(string $bucket, ?string $keyMarker, ?string $versionMarker): array
    {
        $result = $this->client->listObjectVersions(array_filter([
            'Bucket' => $bucket,
            'MaxKeys' => self::PAGE_SIZE,
            'KeyMarker' => $keyMarker,
            'VersionIdMarker' => $versionMarker,
        ]));

        $items = [];

        foreach ((array) ($result->get('Versions') ?? []) as $version) {
            $items[] = [
                'key' => (string) $version['Key'],
                'versionId' => isset($version['VersionId']) ? (string) $version['VersionId'] : null,
                'size' => (int) ($version['Size'] ?? 0),
                'isLatest' => (bool) ($version['IsLatest'] ?? false),
                'isDeleteMarker' => false,
            ];
        }

        foreach ((array) ($result->get('DeleteMarkers') ?? []) as $marker) {
            $items[] = [
                'key' => (string) $marker['Key'],
                'versionId' => isset($marker['VersionId']) ? (string) $marker['VersionId'] : null,
                'size' => 0,
                'isLatest' => (bool) ($marker['IsLatest'] ?? false),
                'isDeleteMarker' => true,
            ];
        }

        $truncated = (bool) $result->get('IsTruncated');

        return [
            'items' => $items,
            'nextKey' => $truncated ? ($result->get('NextKeyMarker') ?: null) : null,
            'nextVersion' => $truncated ? ($result->get('NextVersionIdMarker') ?: null) : null,
        ];
    }

    /**
     * Удаляет до 1000 объектов (или версий) за раз.
     *
     * @param  list<array{key: string, versionId?: string|null}>  $objects
     * @return list<array{key: string, code: string, message: string}> объекты, которые удалить не удалось
     */
    public function deleteObjects(string $bucket, array $objects): array
    {
        if ($objects === []) {
            return [];
        }

        $result = $this->client->deleteObjects([
            'Bucket' => $bucket,
            'Delete' => [
                'Quiet' => true,
                'Objects' => array_map(fn (array $object) => array_filter([
                    'Key' => $object['key'],
                    // У неверсионированных объектов версия «null» — её не передаём
                    'VersionId' => ($object['versionId'] ?? null) === 'null' ? null : ($object['versionId'] ?? null),
                ]), $objects),
            ],
        ]);

        return array_values(array_map(fn (array $error) => [
            'key' => (string) ($error['Key'] ?? ''),
            'code' => (string) ($error['Code'] ?? ''),
            'message' => (string) ($error['Message'] ?? ''),
        ], (array) ($result->get('Errors') ?? [])));
    }

    /**
     * @return array{items: list<array{key: string, uploadId: string}>, nextKey: string|null, nextUploadId: string|null}
     */
    public function listUploadsPage(string $bucket, ?string $keyMarker, ?string $uploadIdMarker): array
    {
        $result = $this->client->listMultipartUploads(array_filter([
            'Bucket' => $bucket,
            'MaxUploads' => self::PAGE_SIZE,
            'KeyMarker' => $keyMarker,
            'UploadIdMarker' => $uploadIdMarker,
        ]));

        $items = array_map(
            fn (array $upload) => ['key' => (string) $upload['Key'], 'uploadId' => (string) $upload['UploadId']],
            (array) ($result->get('Uploads') ?? []),
        );

        $truncated = (bool) $result->get('IsTruncated');

        return [
            'items' => array_values($items),
            'nextKey' => $truncated ? ($result->get('NextKeyMarker') ?: null) : null,
            'nextUploadId' => $truncated ? ($result->get('NextUploadIdMarker') ?: null) : null,
        ];
    }

    public function abortUpload(string $bucket, string $key, string $uploadId): void
    {
        $this->client->abortMultipartUpload(['Bucket' => $bucket, 'Key' => $key, 'UploadId' => $uploadId]);
    }

    /**
     * @return 'Enabled'|'Suspended'|null null — версионирование ни разу не включалось
     */
    public function getVersioning(string $bucket): ?string
    {
        $status = $this->client->getBucketVersioning(['Bucket' => $bucket])->get('Status');

        return in_array($status, ['Enabled', 'Suspended'], true) ? $status : null;
    }

    /**
     * @param  'Enabled'|'Suspended'  $status
     */
    public function putVersioning(string $bucket, string $status): void
    {
        $this->client->putBucketVersioning(['Bucket' => $bucket, 'VersioningConfiguration' => ['Status' => $status]]);
    }

    /**
     * @return list<array{key: string, value: string}>
     */
    public function getTags(string $bucket): array
    {
        try {
            $tags = (array) ($this->client->getBucketTagging(['Bucket' => $bucket])->get('TagSet') ?? []);
        } catch (AwsException $e) {
            if ($this->isMissing($e, 'NoSuchTagSet', 'NoSuchTagSetError')) {
                report_if(config('app.debug'), $e);

                return [];
            }

            throw $e;
        }

        return array_values(array_map(fn (array $tag) => ['key' => (string) $tag['Key'], 'value' => (string) $tag['Value']], $tags));
    }

    /**
     * Пустой список удаляет все теги: часть хранилищ не принимает пустой TagSet.
     *
     * @param  list<array{key: string, value: string}>  $tags
     */
    public function putTags(string $bucket, array $tags): void
    {
        if ($tags === []) {
            $this->client->deleteBucketTagging(['Bucket' => $bucket]);

            return;
        }

        $this->client->putBucketTagging([
            'Bucket' => $bucket,
            'Tagging' => ['TagSet' => array_map(fn (array $tag) => ['Key' => $tag['key'], 'Value' => $tag['value']], $tags)],
        ]);
    }

    /**
     * Политика как JSON-строка; null — политики нет.
     */
    public function getPolicy(string $bucket): ?string
    {
        try {
            $policy = $this->client->getBucketPolicy(['Bucket' => $bucket])->get('Policy');
        } catch (AwsException $e) {
            if ($this->isMissing($e, 'NoSuchBucketPolicy')) {
                report_if(config('app.debug'), $e);

                return null;
            }

            throw $e;
        }

        $policy = is_string($policy) ? $policy : (string) $policy;

        return $policy === '' ? null : $policy;
    }

    public function putPolicy(string $bucket, string $policy): void
    {
        $this->client->putBucketPolicy(['Bucket' => $bucket, 'Policy' => $policy]);
    }

    public function deletePolicy(string $bucket): void
    {
        $this->client->deleteBucketPolicy(['Bucket' => $bucket]);
    }

    /**
     * Правила CORS в формате S3 API (AllowedOrigins, AllowedMethods, ...); пустой список — CORS не настроен.
     *
     * @return list<array<string, mixed>>
     */
    public function getCors(string $bucket): array
    {
        try {
            return array_values((array) ($this->client->getBucketCors(['Bucket' => $bucket])->get('CORSRules') ?? []));
        } catch (AwsException $e) {
            if ($this->isMissing($e, 'NoSuchCORSConfiguration')) {
                report_if(config('app.debug'), $e);

                return [];
            }

            throw $e;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     */
    public function putCors(string $bucket, array $rules): void
    {
        if ($rules === []) {
            $this->client->deleteBucketCors(['Bucket' => $bucket]);

            return;
        }

        $this->client->execute($this->client->getCommand('PutBucketCors', [
            'Bucket' => $bucket,
            'CORSConfiguration' => ['CORSRules' => $rules],
        ]));
    }

    /**
     * Правила жизненного цикла в формате S3 API; пустой список — правил нет.
     *
     * @return list<array<string, mixed>>
     */
    public function getLifecycle(string $bucket): array
    {
        try {
            $rules = (array) ($this->client->getBucketLifecycleConfiguration(['Bucket' => $bucket])->get('Rules') ?? []);
        } catch (AwsException $e) {
            if ($this->isMissing($e, 'NoSuchLifecycleConfiguration')) {
                report_if(config('app.debug'), $e);

                return [];
            }

            throw $e;
        }

        // Пустой фильтр SDK отдаёт как пустой массив; в JSON он стал бы [] вместо объекта
        return array_values(array_map(function (array $rule): array {
            if (array_key_exists('Filter', $rule) && $rule['Filter'] === []) {
                $rule['Filter'] = ['Prefix' => ''];
            }

            return $rule;
        }, $rules));
    }

    /**
     * @param  list<array<string, mixed>>  $rules  пустой список — удалить конфигурацию
     */
    public function putLifecycle(string $bucket, array $rules): void
    {
        if ($rules === []) {
            $this->client->deleteBucketLifecycle(['Bucket' => $bucket]);

            return;
        }

        $this->client->execute($this->client->getCommand('PutBucketLifecycleConfiguration', [
            'Bucket' => $bucket,
            'LifecycleConfiguration' => ['Rules' => $rules],
        ]));
    }

    /**
     * @return array{owner: string|null, grants: list<array{grantee: string, type: string, permission: string}>}
     */
    public function getAcl(string $bucket): array
    {
        $result = $this->client->getBucketAcl(['Bucket' => $bucket]);
        $owner = (array) ($result->get('Owner') ?? []);

        $grants = array_map(function (array $grant): array {
            $grantee = (array) ($grant['Grantee'] ?? []);

            return [
                'grantee' => (string) ($grantee['URI'] ?? $grantee['DisplayName'] ?? $grantee['EmailAddress'] ?? $grantee['ID'] ?? ''),
                'type' => (string) ($grantee['Type'] ?? ''),
                'permission' => (string) ($grant['Permission'] ?? ''),
            ];
        }, (array) ($result->get('Grants') ?? []));

        return [
            'owner' => isset($owner['DisplayName']) || isset($owner['ID']) ? (string) ($owner['DisplayName'] ?? $owner['ID']) : null,
            'grants' => array_values($grants),
        ];
    }

    public function putCannedAcl(string $bucket, string $acl): void
    {
        $this->client->execute($this->client->getCommand('PutBucketAcl', ['Bucket' => $bucket, 'ACL' => $acl]));
    }

    /**
     * Настройки хостинга сайта в формате S3 API; null — хостинг выключен.
     *
     * @return array<string, mixed>|null
     */
    public function getWebsite(string $bucket): ?array
    {
        try {
            $result = $this->client->getBucketWebsite(['Bucket' => $bucket])->toArray();
        } catch (AwsException $e) {
            if ($this->isMissing($e, 'NoSuchWebsiteConfiguration')) {
                report_if(config('app.debug'), $e);

                return null;
            }

            throw $e;
        }

        return array_intersect_key($result, array_flip(['IndexDocument', 'ErrorDocument', 'RedirectAllRequestsTo', 'RoutingRules']));
    }

    /**
     * @param  array<string, mixed>|null  $configuration  null — выключить хостинг
     */
    public function putWebsite(string $bucket, ?array $configuration): void
    {
        if ($configuration === null) {
            $this->client->deleteBucketWebsite(['Bucket' => $bucket]);

            return;
        }

        $this->client->execute($this->client->getCommand('PutBucketWebsite', [
            'Bucket' => $bucket,
            'WebsiteConfiguration' => $configuration,
        ]));
    }

    /**
     * @return array{enabled: bool, mode: string|null, days: int|null, years: int|null}
     */
    public function getObjectLock(string $bucket): array
    {
        try {
            $configuration = (array) ($this->client->getObjectLockConfiguration(['Bucket' => $bucket])->get('ObjectLockConfiguration') ?? []);
        } catch (AwsException $e) {
            if ($this->isMissing($e, 'ObjectLockConfigurationNotFoundError')) {
                report_if(config('app.debug'), $e);

                return ['enabled' => false, 'mode' => null, 'days' => null, 'years' => null];
            }

            throw $e;
        }

        $retention = (array) ($configuration['Rule']['DefaultRetention'] ?? []);

        return [
            'enabled' => ($configuration['ObjectLockEnabled'] ?? null) === 'Enabled',
            'mode' => isset($retention['Mode']) ? (string) $retention['Mode'] : null,
            'days' => isset($retention['Days']) ? (int) $retention['Days'] : null,
            'years' => isset($retention['Years']) ? (int) $retention['Years'] : null,
        ];
    }

    /**
     * Включает Object Lock (необратимо) и задаёт срок хранения по умолчанию. mode = null — без срока по умолчанию.
     */
    public function putObjectLock(string $bucket, ?string $mode, ?int $days, ?int $years): void
    {
        $configuration = ['ObjectLockEnabled' => 'Enabled'];

        if ($mode !== null) {
            $configuration['Rule'] = ['DefaultRetention' => array_filter([
                'Mode' => $mode,
                'Days' => $days,
                'Years' => $years,
            ], fn (mixed $value) => $value !== null)];
        }

        $this->client->execute($this->client->getCommand('PutObjectLockConfiguration', [
            'Bucket' => $bucket,
            'ObjectLockConfiguration' => $configuration,
        ]));
    }

    /**
     * Содержимое «папки»: подпапки (общие префиксы) и объекты на одной странице.
     *
     * @return array{
     *     folders: list<string>,
     *     objects: list<array{key: string, size: int, lastModified: CarbonImmutable|null, etag: string|null, storageClass: string|null}>,
     *     next: string|null
     * }
     */
    public function listFolder(string $bucket, string $prefix, ?string $token, int $limit): array
    {
        $result = $this->client->listObjectsV2(array_filter([
            'Bucket' => $bucket,
            'Prefix' => $prefix === '' ? null : $prefix,
            'Delimiter' => '/',
            'MaxKeys' => $limit,
            'ContinuationToken' => $token,
        ]));

        $folders = array_map(fn (array $item) => (string) $item['Prefix'], (array) ($result->get('CommonPrefixes') ?? []));

        $objects = [];

        foreach ((array) ($result->get('Contents') ?? []) as $object) {
            $key = (string) $object['Key'];

            // Сам маркер папки («prefix/») показывать как объект не нужно
            if ($key === $prefix) {
                continue;
            }

            $objects[] = [
                'key' => $key,
                'size' => (int) ($object['Size'] ?? 0),
                'lastModified' => ($object['LastModified'] ?? null) instanceof DateTimeInterface ? CarbonImmutable::instance($object['LastModified']) : null,
                'etag' => isset($object['ETag']) ? trim((string) $object['ETag'], '"') : null,
                'storageClass' => isset($object['StorageClass']) ? (string) $object['StorageClass'] : null,
            ];
        }

        return [
            'folders' => array_values($folders),
            'objects' => $objects,
            'next' => $result->get('IsTruncated') ? ($result->get('NextContinuationToken') ?: null) : null,
        ];
    }

    /**
     * Страница объектов с префиксом (без разделителя) — для операций над «папкой».
     *
     * @return array{items: list<array{key: string, size: int}>, next: string|null}
     */
    public function listPrefixPage(string $bucket, string $prefix, ?string $token): array
    {
        $result = $this->client->listObjectsV2(array_filter([
            'Bucket' => $bucket,
            'Prefix' => $prefix,
            'MaxKeys' => self::PAGE_SIZE,
            'ContinuationToken' => $token,
        ]));

        return [
            'items' => array_values(array_map(
                fn (array $object) => ['key' => (string) $object['Key'], 'size' => (int) ($object['Size'] ?? 0)],
                (array) ($result->get('Contents') ?? []),
            )),
            'next' => $result->get('IsTruncated') ? ($result->get('NextContinuationToken') ?: null) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function headObject(string $bucket, string $key, ?string $versionId = null): array
    {
        return $this->client->headObject(array_filter(['Bucket' => $bucket, 'Key' => $key, 'VersionId' => $versionId]))->toArray();
    }

    /**
     * Подписанная ссылка на скачивание. attachment — отдавать как файл (Content-Disposition), иначе браузер откроет сам.
     */
    public function presignGet(string $bucket, string $key, ?string $versionId, int $seconds, ?string $downloadName): string
    {
        $presigner = ($this->presigner)();

        $command = $presigner->getCommand('GetObject', array_filter([
            'Bucket' => $bucket,
            'Key' => $key,
            'VersionId' => $versionId,
            'ResponseContentDisposition' => $downloadName === null
                ? null
                : "attachment; filename*=UTF-8''".rawurlencode($downloadName),
        ]));

        return (string) $presigner->createPresignedRequest($command, "+{$seconds} seconds")->getUri();
    }

    /**
     * @param  resource  $body
     */
    public function putObject(string $bucket, string $key, $body, ?string $contentType): void
    {
        $this->client->putObject(array_filter([
            'Bucket' => $bucket,
            'Key' => $key,
            'Body' => $body,
            'ContentType' => $contentType,
        ], fn (mixed $value) => $value !== null));
    }

    public function createMultipartUpload(string $bucket, string $key, ?string $contentType): string
    {
        return (string) $this->client->createMultipartUpload(array_filter([
            'Bucket' => $bucket,
            'Key' => $key,
            'ContentType' => $contentType,
        ]))->get('UploadId');
    }

    /**
     * @param  resource  $body
     */
    public function uploadPart(string $bucket, string $key, string $uploadId, int $partNumber, $body): string
    {
        return trim((string) $this->client->uploadPart([
            'Bucket' => $bucket,
            'Key' => $key,
            'UploadId' => $uploadId,
            'PartNumber' => $partNumber,
            'Body' => $body,
        ])->get('ETag'), '"');
    }

    /**
     * @param  list<array{partNumber: int, etag: string}>  $parts
     */
    public function completeMultipartUpload(string $bucket, string $key, string $uploadId, array $parts): void
    {
        $this->client->completeMultipartUpload([
            'Bucket' => $bucket,
            'Key' => $key,
            'UploadId' => $uploadId,
            'MultipartUpload' => ['Parts' => array_map(
                fn (array $part) => ['PartNumber' => $part['partNumber'], 'ETag' => '"'.$part['etag'].'"'],
                $parts,
            )],
        ]);
    }

    /**
     * Копирование объекта; крупные объекты (больше 5 ГБ) SDK копирует по частям.
     *
     * @param  array<string, mixed>  $params  дополнительные параметры (например, метаданные)
     */
    public function copyObject(string $sourceBucket, string $sourceKey, ?string $versionId, string $bucket, string $key, array $params = []): void
    {
        $source = array_filter(['Bucket' => $sourceBucket, 'Key' => $sourceKey, 'VersionId' => $versionId]);

        (new ObjectCopier($this->client, $source, ['Bucket' => $bucket, 'Key' => $key], 'private', [
            'params' => $params,
            'mup_threshold' => 5 * 1024 * 1024 * 1024,
        ]))->copy();
    }

    /**
     * Замена метаданных объекта: копирование в себя с MetadataDirective=REPLACE (до 5 ГБ).
     *
     * @param  array<string, string>  $metadata  пользовательские метаданные (x-amz-meta-*)
     */
    public function replaceMetadata(string $bucket, string $key, ?string $contentType, ?string $cacheControl, ?string $contentDisposition, array $metadata): void
    {
        $this->client->copyObject(array_filter([
            'Bucket' => $bucket,
            'Key' => $key,
            'CopySource' => $bucket.'/'.str_replace('%2F', '/', rawurlencode($key)),
            'MetadataDirective' => 'REPLACE',
            'ContentType' => $contentType,
            'CacheControl' => $cacheControl,
            'ContentDisposition' => $contentDisposition,
            'Metadata' => $metadata,
        ], fn (mixed $value) => $value !== null));
    }

    /**
     * @return list<array{key: string, value: string}>
     */
    public function getObjectTags(string $bucket, string $key, ?string $versionId = null): array
    {
        $tags = (array) ($this->client->getObjectTagging(array_filter(['Bucket' => $bucket, 'Key' => $key, 'VersionId' => $versionId]))->get('TagSet') ?? []);

        return array_values(array_map(fn (array $tag) => ['key' => (string) $tag['Key'], 'value' => (string) $tag['Value']], $tags));
    }

    /**
     * @param  list<array{key: string, value: string}>  $tags
     */
    public function putObjectTags(string $bucket, string $key, array $tags): void
    {
        if ($tags === []) {
            $this->client->deleteObjectTagging(['Bucket' => $bucket, 'Key' => $key]);

            return;
        }

        $this->client->putObjectTagging([
            'Bucket' => $bucket,
            'Key' => $key,
            'Tagging' => ['TagSet' => array_map(fn (array $tag) => ['Key' => $tag['key'], 'Value' => $tag['value']], $tags)],
        ]);
    }

    /**
     * Все версии одного объекта, новые первыми.
     *
     * @return list<array{versionId: string|null, size: int, lastModified: CarbonImmutable|null, isLatest: bool, isDeleteMarker: bool}>
     */
    public function objectVersions(string $bucket, string $key): array
    {
        $versions = [];
        $keyMarker = null;
        $versionMarker = null;

        do {
            $page = $this->client->listObjectVersions(array_filter([
                'Bucket' => $bucket,
                'Prefix' => $key,
                'KeyMarker' => $keyMarker,
                'VersionIdMarker' => $versionMarker,
            ]));

            foreach ([['Versions', false], ['DeleteMarkers', true]] as [$field, $isMarker]) {
                foreach ((array) ($page->get($field) ?? []) as $item) {
                    // Prefix находит и другие ключи, начинающиеся так же
                    if ((string) $item['Key'] !== $key) {
                        continue;
                    }

                    $versions[] = [
                        'versionId' => isset($item['VersionId']) ? (string) $item['VersionId'] : null,
                        'size' => (int) ($item['Size'] ?? 0),
                        'lastModified' => ($item['LastModified'] ?? null) instanceof DateTimeInterface ? CarbonImmutable::instance($item['LastModified']) : null,
                        'isLatest' => (bool) ($item['IsLatest'] ?? false),
                        'isDeleteMarker' => $isMarker,
                    ];
                }
            }

            $truncated = (bool) $page->get('IsTruncated');
            $keyMarker = $page->get('NextKeyMarker');
            $versionMarker = $page->get('NextVersionIdMarker');

            // Дальше идут уже другие ключи
            if ($keyMarker !== null && $keyMarker !== $key) {
                break;
            }
        } while ($truncated);

        usort($versions, fn (array $a, array $b) => ($b['lastModified']?->getTimestamp() ?? 0) <=> ($a['lastModified']?->getTimestamp() ?? 0));

        return $versions;
    }

    /**
     * @return array{owner: string|null, grants: list<array{grantee: string, type: string, permission: string}>}
     */
    public function getObjectAcl(string $bucket, string $key): array
    {
        $result = $this->client->getObjectAcl(['Bucket' => $bucket, 'Key' => $key]);
        $owner = (array) ($result->get('Owner') ?? []);

        return [
            'owner' => isset($owner['DisplayName']) || isset($owner['ID']) ? (string) ($owner['DisplayName'] ?? $owner['ID']) : null,
            'grants' => array_values(array_map(function (array $grant): array {
                $grantee = (array) ($grant['Grantee'] ?? []);

                return [
                    'grantee' => (string) ($grantee['URI'] ?? $grantee['DisplayName'] ?? $grantee['EmailAddress'] ?? $grantee['ID'] ?? ''),
                    'type' => (string) ($grantee['Type'] ?? ''),
                    'permission' => (string) ($grant['Permission'] ?? ''),
                ];
            }, (array) ($result->get('Grants') ?? []))),
        ];
    }

    public function putObjectCannedAcl(string $bucket, string $key, string $acl): void
    {
        $this->client->execute($this->client->getCommand('PutObjectAcl', ['Bucket' => $bucket, 'Key' => $key, 'ACL' => $acl]));
    }

    /**
     * @return array{mode: string|null, until: CarbonImmutable|null, legalHold: bool}
     */
    public function getObjectProtection(string $bucket, string $key, ?string $versionId): array
    {
        $params = array_filter(['Bucket' => $bucket, 'Key' => $key, 'VersionId' => $versionId]);

        try {
            $retention = (array) ($this->client->getObjectRetention($params)->get('Retention') ?? []);
        } catch (AwsException $e) {
            if (! $this->isMissing($e, 'NoSuchObjectLockConfiguration', 'ObjectLockConfigurationNotFoundError', 'InvalidRequest')) {
                throw $e;
            }

            report_if(config('app.debug'), $e);

            $retention = [];
        }

        try {
            $hold = (array) ($this->client->getObjectLegalHold($params)->get('LegalHold') ?? []);
        } catch (AwsException $e) {
            if (! $this->isMissing($e, 'NoSuchObjectLockConfiguration', 'ObjectLockConfigurationNotFoundError', 'InvalidRequest')) {
                throw $e;
            }

            report_if(config('app.debug'), $e);

            $hold = [];
        }

        $until = $retention['RetainUntilDate'] ?? null;

        return [
            'mode' => isset($retention['Mode']) ? (string) $retention['Mode'] : null,
            'until' => $until instanceof DateTimeInterface ? CarbonImmutable::instance($until) : (is_string($until) ? CarbonImmutable::parse($until) : null),
            'legalHold' => ($hold['Status'] ?? null) === 'ON',
        ];
    }

    public function putObjectRetention(string $bucket, string $key, ?string $versionId, string $mode, DateTimeInterface $until): void
    {
        $this->client->execute($this->client->getCommand('PutObjectRetention', array_filter([
            'Bucket' => $bucket,
            'Key' => $key,
            'VersionId' => $versionId,
            'Retention' => ['Mode' => $mode, 'RetainUntilDate' => $until],
        ])));
    }

    public function putObjectLegalHold(string $bucket, string $key, ?string $versionId, bool $on): void
    {
        $this->client->putObjectLegalHold(array_filter([
            'Bucket' => $bucket,
            'Key' => $key,
            'VersionId' => $versionId,
            'LegalHold' => ['Status' => $on ? 'ON' : 'OFF'],
        ]));
    }

    /**
     * Незавершённые multipart-загрузки бакета (одна страница).
     *
     * @return list<array{key: string, uploadId: string, initiated: CarbonImmutable|null}>
     */
    public function listUploads(string $bucket, int $limit): array
    {
        $result = $this->client->listMultipartUploads(['Bucket' => $bucket, 'MaxUploads' => $limit]);

        return array_values(array_map(fn (array $upload) => [
            'key' => (string) $upload['Key'],
            'uploadId' => (string) $upload['UploadId'],
            'initiated' => ($upload['Initiated'] ?? null) instanceof DateTimeInterface ? CarbonImmutable::instance($upload['Initiated']) : null,
        ], (array) ($result->get('Uploads') ?? [])));
    }

    /**
     * «Настройка отсутствует» у провайдеров выражается по-разному: свой код ошибки или просто 404.
     */
    private function isMissing(AwsException $e, string ...$codes): bool
    {
        return in_array($e->getAwsErrorCode(), $codes, true)
            || ($e->getStatusCode() === 404 && $e->getAwsErrorCode() !== 'NoSuchBucket');
    }
}
