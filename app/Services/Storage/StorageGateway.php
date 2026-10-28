<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Domain\Enum\Storage\StorageCapability;
use App\Domain\Enum\Storage\StorageProvider;
use Aws\S3\S3Client;
use Carbon\CarbonImmutable;
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
     * @param  list<StorageCapability>  $capabilities
     * @param  list<string>|null  $fixedBuckets
     */
    public function __construct(
        private S3Client $client,
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
}
