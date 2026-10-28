<?php

use App\Domain\DTO\Bucket\BucketCreateData;
use App\Domain\Enum\Operation\OperationStatus;
use App\Domain\Enum\Storage\StorageProvider;
use App\Models\BackgroundOperation;
use App\Models\BucketStats;
use App\Models\Storage;
use App\Services\Bucket\BucketService;
use App\Services\Storage\StorageGatewayFactory;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;

/*
 * Полный цикл на настоящем MinIO: версии, маркеры удаления, незавершённые загрузки.
 *   docker compose --profile s3 up -d minio
 *   docker compose run --rm tools vendor/bin/pest tests/Integration
 */

beforeEach(function () {
    $socket = @fsockopen('minio', 9000, $errno, $error, 1);

    if ($socket === false) {
        $this->markTestSkipped('MinIO is not running.');
    }

    fclose($socket);

    config(['queue.default' => 'sync', 's3toolkit.operations.step_seconds' => 30]);

    $this->storage = Storage::factory()->create([
        'provider' => StorageProvider::Minio,
        'endpoint' => 'http://minio:9000',
        'access_key_id' => env('MINIO_ROOT_USER', 'minioadmin'),
        'secret_access_key' => env('MINIO_ROOT_PASSWORD', 'minioadmin'),
        'verify_ssl' => false,
    ]);
    $this->bucket = 'it-ops-'.bin2hex(random_bytes(4));
    $this->client = new S3Client([
        'version' => 'latest', 'region' => 'us-east-1', 'endpoint' => 'http://minio:9000', 'use_path_style_endpoint' => true,
        'credentials' => ['key' => env('MINIO_ROOT_USER', 'minioadmin'), 'secret' => env('MINIO_ROOT_PASSWORD', 'minioadmin')],
    ]);
});

it('creates a bucket, counts versions and deletes everything', function () {
    $this->actingAs(superadmin());
    $buckets = app(BucketService::class);

    $buckets->create($this->storage->id, new BucketCreateData(['name' => $this->bucket]));

    $this->client->putBucketVersioning(['Bucket' => $this->bucket, 'VersioningConfiguration' => ['Status' => 'Enabled']]);
    $this->client->putObject(['Bucket' => $this->bucket, 'Key' => 'a.txt', 'Body' => 'one']);
    $this->client->putObject(['Bucket' => $this->bucket, 'Key' => 'a.txt', 'Body' => 'two!']);
    $this->client->putObject(['Bucket' => $this->bucket, 'Key' => 'dir/b.txt', 'Body' => '12345']);
    $this->client->deleteObject(['Bucket' => $this->bucket, 'Key' => 'dir/b.txt']);
    $this->client->createMultipartUpload(['Bucket' => $this->bucket, 'Key' => 'unfinished.bin']);

    expect(collect($buckets->list($this->storage->id)->buckets)->pluck('name'))->toContain($this->bucket);

    $buckets->requestStats(auth()->id(), $this->storage->id, $this->bucket);

    expect(BucketStats::query()->firstOrFail())
        ->object_count->toBe(1)
        ->total_size->toBe(4)
        ->version_count->toBe(4)
        ->versions_size->toBe(12);

    $buckets->requestDeletion(auth()->id(), $this->storage->id, $this->bucket);

    $operation = BackgroundOperation::query()->latest('id')->firstOrFail();

    expect($operation)->status->toBe(OperationStatus::Completed)->processed->toBe(4)
        ->and(fn () => $this->client->headBucket(['Bucket' => $this->bucket]))->toThrow(S3Exception::class)
        ->and(BucketStats::query()->count())->toBe(0);
});

it('lists buckets through the gateway', function () {
    $this->client->createBucket(['Bucket' => $this->bucket]);

    try {
        $names = array_column(app(StorageGatewayFactory::class)->forStorage($this->storage->id)->listBuckets(), 'name');

        expect($names)->toContain($this->bucket);
    } finally {
        $this->client->deleteBucket(['Bucket' => $this->bucket]);
    }
});
