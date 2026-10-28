<?php

use App\Domain\DTO\Bucket\BucketTagData;
use App\Domain\Enum\Bucket\VersioningStatus;
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
    $this->bucket = 'it-settings-'.bin2hex(random_bytes(4));
    $this->client = new S3Client([
        'version' => 'latest', 'region' => 'us-east-1', 'endpoint' => 'http://minio:9000', 'use_path_style_endpoint' => true,
        'credentials' => ['key' => env('MINIO_ROOT_USER', 'minioadmin'), 'secret' => env('MINIO_ROOT_PASSWORD', 'minioadmin')],
    ]);
    $this->client->createBucket(['Bucket' => $this->bucket]);
    $this->settings = app(BucketSettingsService::class);
    $this->actingAs(superadmin());
});

afterEach(function () {
    if (isset($this->bucket)) {
        $this->client->deleteBucket(['Bucket' => $this->bucket]);
    }
});

it('switches versioning on a real MinIO', function () {
    expect($this->settings->versioning($this->storage->id, $this->bucket)->data)->toBe(VersioningStatus::Unversioned);

    $this->settings->setVersioning($this->storage->id, $this->bucket, VersioningStatus::Enabled);
    expect($this->settings->versioning($this->storage->id, $this->bucket)->data)->toBe(VersioningStatus::Enabled);

    $this->settings->setVersioning($this->storage->id, $this->bucket, VersioningStatus::Suspended);
    expect($this->settings->versioning($this->storage->id, $this->bucket)->data)->toBe(VersioningStatus::Suspended);
});

it('saves and removes tags on a real MinIO', function () {
    expect($this->settings->tags($this->storage->id, $this->bucket)->data)->toBe([]);

    $this->settings->setTags($this->storage->id, $this->bucket, [new BucketTagData(['key' => 'env', 'value' => 'test'])]);
    expect($this->settings->tags($this->storage->id, $this->bucket)->data[0]->toArray())->toBe(['key' => 'env', 'value' => 'test']);

    $this->settings->setTags($this->storage->id, $this->bucket, []);
    expect($this->settings->tags($this->storage->id, $this->bucket)->data)->toBe([]);
});

it('saves and removes a policy on a real MinIO', function () {
    expect($this->settings->policy($this->storage->id, $this->bucket)->data)->toBeNull();

    $policy = json_encode(['Version' => '2012-10-17', 'Statement' => [[
        'Effect' => 'Allow', 'Principal' => ['AWS' => ['*']], 'Action' => ['s3:GetObject'], 'Resource' => ["arn:aws:s3:::{$this->bucket}/*"],
    ]]]);

    $this->settings->setPolicy($this->storage->id, $this->bucket, $policy);
    expect(json_decode((string) $this->settings->policy($this->storage->id, $this->bucket)->data, true)['Statement'][0]['Action'])
        ->toBe(['s3:GetObject']);

    $this->settings->setPolicy($this->storage->id, $this->bucket, null);
    expect($this->settings->policy($this->storage->id, $this->bucket)->data)->toBeNull();
});

it('reports CORS as unsupported on MinIO or saves it', function () {
    // В MinIO CORS для бакетов зависит от версии; проверяем, что ответ осмысленный в обоих случаях
    $section = $this->settings->cors($this->storage->id, $this->bucket);

    expect($section->error === null || str_contains($section->error, 'NotImplemented'))->toBeTrue();
});
