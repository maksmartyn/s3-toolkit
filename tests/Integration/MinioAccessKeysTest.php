<?php

use App\Domain\DTO\AccessKey\AccessKeyCreateData;
use App\Domain\Enum\AccessKey\AccessKeyStatus;
use App\Domain\Enum\Storage\StorageProvider;
use App\Models\Storage;
use App\Services\AccessKey\AccessKeyService;
use Aws\S3\S3Client;

/*
 * Ключи MinIO через mc на настоящем MinIO: создание, проверка ключом, отключение, удаление.
 */

beforeEach(function () {
    $socket = @fsockopen('minio', 9000, $errno, $error, 1);

    if ($socket === false || trim((string) shell_exec('command -v mc')) === '') {
        $this->markTestSkipped('MinIO or mc is not available.');
    }

    fclose($socket);

    $this->storage = Storage::factory()->create([
        'provider' => StorageProvider::Minio,
        'endpoint' => 'http://minio:9000',
        'access_key_id' => env('MINIO_ROOT_USER', 'minioadmin'),
        'secret_access_key' => env('MINIO_ROOT_PASSWORD', 'minioadmin'),
        'admin_credentials' => [],
    ]);
    $this->actingAs(superadmin());
});

it('creates, lists, disables and deletes a service account', function () {
    $keys = app(AccessKeyService::class);

    $secret = $keys->create($this->storage->id, new AccessKeyCreateData([
        'name' => 'it-key',
        'description' => 'integration',
        'expiresAt' => now()->addDay()->toImmutable(),
    ]));

    try {
        $client = new S3Client([
            'version' => 'latest', 'region' => 'us-east-1', 'endpoint' => 'http://minio:9000', 'use_path_style_endpoint' => true,
            'credentials' => ['key' => $secret->accessKeyId, 'secret' => $secret->secretAccessKey],
        ]);
        expect($client->listBuckets()->get('Buckets'))->toBeArray();

        $listed = collect($keys->overview($this->storage->id, null)->keys)->firstWhere('accessKeyId', $secret->accessKeyId);
        expect($listed)->not->toBeNull()
            ->and($listed->name)->toBe('it-key')
            ->and($listed->status)->toBe(AccessKeyStatus::Active)
            ->and($listed->expiresAt)->not->toBeNull();

        $keys->setStatus($this->storage->id, $secret->accessKeyId, null, false);
        $listed = collect($keys->overview($this->storage->id, null)->keys)->firstWhere('accessKeyId', $secret->accessKeyId);
        expect($listed->status)->toBe(AccessKeyStatus::Inactive);
    } finally {
        $keys->delete($this->storage->id, $secret->accessKeyId, null);
    }

    expect(collect($keys->overview($this->storage->id, null)->keys)->pluck('accessKeyId'))->not->toContain($secret->accessKeyId);
});
