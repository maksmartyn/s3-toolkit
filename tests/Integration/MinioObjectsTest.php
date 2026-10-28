<?php

use App\Domain\Enum\Operation\OperationStatus;
use App\Domain\Enum\Storage\StorageProvider;
use App\Models\BackgroundOperation;
use App\Models\Storage;
use Aws\S3\S3Client;
use Illuminate\Http\UploadedFile;

/*
 * Обозреватель объектов на настоящем MinIO через HTTP-маршруты приложения.
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
    ]);
    $this->client = new S3Client([
        'version' => 'latest', 'region' => 'us-east-1', 'endpoint' => 'http://minio:9000', 'use_path_style_endpoint' => true,
        'credentials' => ['key' => env('MINIO_ROOT_USER', 'minioadmin'), 'secret' => env('MINIO_ROOT_PASSWORD', 'minioadmin')],
    ]);
    $this->bucket = 'it-obj-'.bin2hex(random_bytes(4));
    $this->other = $this->bucket.'-b';
    $this->client->createBucket(['Bucket' => $this->bucket]);
    $this->client->createBucket(['Bucket' => $this->other]);
    $this->url = fn (string $name, array $query = [], ?string $bucket = null) => route(
        'storages.buckets.objects.'.$name,
        ['storage' => $this->storage, 'bucket' => $bucket ?? $this->bucket, ...$query],
    );
    $this->keys = function (string $bucket, string $prefix = ''): array {
        $keys = array_column((array) $this->client->listObjectsV2(['Bucket' => $bucket, 'Prefix' => $prefix])->get('Contents'), 'Key');
        sort($keys);

        return $keys;
    };

    $this->actingAs(superadmin());
});

afterEach(function () {
    if (! isset($this->client)) {
        return;
    }

    foreach ([$this->bucket, $this->other] as $bucket) {
        $versions = $this->client->listObjectVersions(['Bucket' => $bucket]);
        $objects = [...(array) $versions->get('Versions'), ...(array) $versions->get('DeleteMarkers')];

        if ($objects !== []) {
            $this->client->deleteObjects(['Bucket' => $bucket, 'Delete' => ['Objects' => array_map(
                fn (array $object) => ['Key' => $object['Key'], 'VersionId' => $object['VersionId']],
                $objects,
            )]]);
        }

        $this->client->deleteBucket(['Bucket' => $bucket]);
    }
});

it('uploads small and multipart files, lists, renames and deletes', function () {
    $this->post(($this->url)('upload.store'), [
        'key' => 'docs/hello.txt',
        'file' => UploadedFile::fake()->createWithContent('hello.txt', 'hello'),
    ], ['Accept' => 'application/json'])->assertOk();

    $uploadId = $this->postJson(($this->url)('upload.start'), ['key' => 'big.bin', 'content_type' => 'application/octet-stream'])
        ->assertOk()->json('uploadId');
    $first = $this->post(($this->url)('upload.part'), [
        'key' => 'big.bin', 'upload_id' => $uploadId, 'part_number' => 1,
        'file' => UploadedFile::fake()->createWithContent('part', str_repeat('a', 5 * 1024 * 1024)),
    ], ['Accept' => 'application/json'])->assertOk()->json('etag');
    $second = $this->post(($this->url)('upload.part'), [
        'key' => 'big.bin', 'upload_id' => $uploadId, 'part_number' => 2,
        'file' => UploadedFile::fake()->createWithContent('part', 'tail'),
    ], ['Accept' => 'application/json'])->assertOk()->json('etag');
    $this->postJson(($this->url)('upload.complete'), ['key' => 'big.bin', 'upload_id' => $uploadId, 'parts' => [
        ['part_number' => 2, 'etag' => $second], ['part_number' => 1, 'etag' => $first],
    ]])->assertOk();

    expect($this->client->headObject(['Bucket' => $this->bucket, 'Key' => 'big.bin'])->get('ContentLength'))->toBe(5 * 1024 * 1024 + 4);

    $this->postJson(($this->url)('folder'), ['prefix' => 'docs/', 'name' => 'empty'])->assertOk();

    $root = $this->getJson(($this->url)('list'))->assertOk();
    expect($root->json('folders'))->toBe(['docs/'])->and(array_column($root->json('objects'), 'name'))->toBe(['big.bin']);

    $docs = $this->getJson(($this->url)('list', ['prefix' => 'docs/']))->assertOk();
    expect($docs->json('folders'))->toBe(['docs/empty/'])->and(array_column($docs->json('objects'), 'name'))->toBe(['hello.txt']);

    $this->getJson(($this->url)('details', ['key' => 'docs/hello.txt']))
        ->assertOk()->assertJsonPath('size', 5)->assertJsonPath('contentType', 'text/plain');

    $this->putJson(($this->url)('metadata'), [
        'key' => 'docs/hello.txt', 'content_type' => 'text/markdown', 'metadata' => [['key' => 'Owner', 'value' => 'team-a']],
    ])->assertOk();
    $this->getJson(($this->url)('details', ['key' => 'docs/hello.txt']))
        ->assertJsonPath('contentType', 'text/markdown')->assertJsonPath('metadata.owner', 'team-a');

    $this->putJson(($this->url)('tags'), ['key' => 'docs/hello.txt', 'tags' => [['key' => 'env', 'value' => 'prod']]])->assertOk();
    $this->getJson(($this->url)('details', ['key' => 'docs/hello.txt']))->assertJsonPath('tags.0.key', 'env');

    $this->postJson(($this->url)('rename'), ['key' => 'docs/hello.txt', 'new_key' => 'docs/renamed.txt'])->assertOk();
    expect(($this->keys)($this->bucket, 'docs/'))->toBe(['docs/empty/', 'docs/renamed.txt']);

    $this->deleteJson(($this->url)('destroy'), ['objects' => [['key' => 'big.bin'], ['key' => 'docs/renamed.txt']]])->assertOk();
    expect(($this->keys)($this->bucket))->toBe(['docs/empty/']);
});

it('copies, moves and deletes folders in the background', function () {
    foreach (['src/a.txt', 'src/sub/b.txt', 'src/sub/c.txt', 'srcx/keep.txt'] as $key) {
        $this->client->putObject(['Bucket' => $this->bucket, 'Key' => $key, 'Body' => $key]);
    }

    $this->postJson(($this->url)('folder-operation'), ['type' => 'copy', 'prefix' => 'src', 'target_bucket' => $this->other, 'target_prefix' => 'backup'])
        ->assertOk();
    expect(($this->keys)($this->other))->toBe(['backup/src/a.txt', 'backup/src/sub/b.txt', 'backup/src/sub/c.txt']);

    $this->postJson(($this->url)('folder-operation'), ['type' => 'move', 'prefix' => 'src/sub/', 'target_bucket' => $this->bucket, 'target_prefix' => ''])
        ->assertOk();
    expect(($this->keys)($this->bucket))->toBe(['src/a.txt', 'srcx/keep.txt', 'sub/b.txt', 'sub/c.txt']);

    $this->postJson(($this->url)('folder-operation'), ['type' => 'delete', 'prefix' => 'src/'])->assertOk();
    expect(($this->keys)($this->bucket))->toBe(['srcx/keep.txt', 'sub/b.txt', 'sub/c.txt'])
        ->and(BackgroundOperation::query()->pluck('status')->all())->each->toBe(OperationStatus::Completed)
        ->and(BackgroundOperation::query()->latest('id')->firstOrFail()->processed)->toBe(1);

    $this->postJson(($this->url)('transfer'), [
        'keys' => ['sub/b.txt'], 'source_prefix' => 'sub/', 'target_bucket' => $this->other, 'target_prefix' => 'one', 'move' => false,
    ])->assertOk();
    expect(($this->keys)($this->other, 'one/'))->toBe(['one/b.txt']);
});

it('lists, restores and deletes versions', function () {
    $this->client->putBucketVersioning(['Bucket' => $this->bucket, 'VersioningConfiguration' => ['Status' => 'Enabled']]);
    $old = $this->client->putObject(['Bucket' => $this->bucket, 'Key' => 'v.txt', 'Body' => 'old'])->get('VersionId');
    $this->client->putObject(['Bucket' => $this->bucket, 'Key' => 'v.txt', 'Body' => 'newer']);
    $this->client->putObject(['Bucket' => $this->bucket, 'Key' => 'v.txt.bak', 'Body' => 'other key']);

    $versions = $this->getJson(($this->url)('versions', ['key' => 'v.txt']))->assertOk()->json();
    expect($versions)->toHaveCount(2)->and($versions[0]['isLatest'])->toBeTrue();

    $this->postJson(($this->url)('restore'), ['key' => 'v.txt', 'version' => $old])->assertOk();
    expect((string) $this->client->getObject(['Bucket' => $this->bucket, 'Key' => 'v.txt'])->get('Body'))->toBe('old');

    $this->deleteJson(($this->url)('destroy'), ['objects' => [['key' => 'v.txt', 'version' => $old]]])->assertOk();
    expect($this->getJson(($this->url)('versions', ['key' => 'v.txt']))->json())->toHaveCount(2);

    $this->get(($this->url)('download', ['key' => 'v.txt']))->assertRedirectContains('http://minio:9000/'.$this->bucket.'/v.txt');
});
