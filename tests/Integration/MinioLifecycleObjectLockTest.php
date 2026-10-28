<?php

use App\Domain\DTO\Bucket\ObjectLockData;
use App\Domain\Enum\Bucket\RetentionMode;
use App\Domain\Enum\Storage\StorageProvider;
use App\Models\Storage;
use App\Services\Bucket\BucketSettingsService;
use Aws\S3\S3Client;

beforeEach(function () {
    $socket = @fsockopen('minio', 9000, $errno, $error, 1);

    if ($socket === false) {
        $this->markTestSkipped('MinIO is not running.');
    }

    fclose($socket);

    $this->storage = Storage::factory()->create([
        'provider' => StorageProvider::Minio,
        'endpoint' => 'http://minio:9000',
        'access_key_id' => env('MINIO_ROOT_USER', 'minioadmin'),
        'secret_access_key' => env('MINIO_ROOT_PASSWORD', 'minioadmin'),
    ]);
    $this->client = new S3Client([
        'version' => 'latest', 'region' => 'us-east-1', 'endpoint' => 'http://minio:9000', 'use_path_style_endpoint' => true,
        'credentials' => ['key' => env('MINIO_ROOT_USER', 'minioadmin'), 'secret' => env('MINIO_ROOT_PASSWORD', 'minioadmin')],
    ]);
    $this->settings = app(BucketSettingsService::class);
    $this->actingAs(superadmin());
});

it('saves and removes lifecycle rules on a real MinIO', function () {
    $bucket = 'it-lc-'.bin2hex(random_bytes(4));
    $this->client->createBucket(['Bucket' => $bucket]);

    try {
        $this->settings->setLifecycle($this->storage->id, $bucket, [[
            'ID' => 'cleanup', 'Status' => 'Enabled', 'Filter' => ['Prefix' => 'tmp/'],
            'Expiration' => ['Days' => 3], 'AbortIncompleteMultipartUpload' => ['DaysAfterInitiation' => 1],
        ]]);

        $rules = $this->settings->lifecycle($this->storage->id, $bucket)->data;
        expect($rules[0]['ID'])->toBe('cleanup')->and($rules[0]['Expiration']['Days'])->toBe(3);

        $this->settings->setLifecycle($this->storage->id, $bucket, []);
        expect($this->settings->lifecycle($this->storage->id, $bucket)->data)->toBe([]);
    } finally {
        $this->client->deleteBucket(['Bucket' => $bucket]);
    }
});

it('sets default retention on an Object Lock bucket on a real MinIO', function () {
    $bucket = 'it-lock-'.bin2hex(random_bytes(4));
    $this->client->createBucket(['Bucket' => $bucket, 'ObjectLockEnabledForBucket' => true]);

    try {
        expect($this->settings->objectLock($this->storage->id, $bucket)->data->enabled)->toBeTrue();

        $this->settings->setObjectLock($this->storage->id, $bucket, new ObjectLockData([
            'enabled' => true, 'mode' => RetentionMode::Governance, 'days' => 1,
        ]));

        expect($this->settings->objectLock($this->storage->id, $bucket)->data)
            ->mode->toBe(RetentionMode::Governance)
            ->days->toBe(1);
    } finally {
        $this->client->deleteBucket(['Bucket' => $bucket]);
    }
});
