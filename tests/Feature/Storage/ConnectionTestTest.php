<?php

use App\Domain\Enum\Permission\SystemPermission;
use App\Models\Storage;
use App\Services\Storage\EndpointGuard;
use Aws\Command;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use GuzzleHttp\Psr7\Response;

function s3Error(string $code, int $status): Closure
{
    return fn (Command $command) => new S3Exception('error', $command, [
        'code' => $code,
        'response' => new Response($status),
    ]);
}

function connectionPayload(array $overrides = []): array
{
    return [
        'provider' => 'minio',
        'endpoint' => 'http://minio:9000',
        'region' => 'us-east-1',
        'use_path_style' => true,
        'verify_ssl' => true,
        'access_key_id' => 'KEY12345',
        'secret_access_key' => 'secret',
        ...$overrides,
    ];
}

it('lists buckets on success', function () {
    $s3 = fakeS3(new Result(['Buckets' => [['Name' => 'a'], ['Name' => 'b']]]));

    $this->actingAs(userWith(SystemPermission::ManageStorages))
        ->postJson(route('storages.test-connection'), connectionPayload())
        ->assertOk()
        ->assertJson(['ok' => true, 'bucketCount' => 2, 'buckets' => ['a', 'b']]);

    expect($s3->connections[0])
        ->endpoint->toBe('http://minio:9000')
        ->usePathStyle->toBeTrue()
        ->and($s3->handler->getLastCommand()->getName())->toBe('ListBuckets');
});

it('checks listed buckets with HeadBucket instead of ListBuckets', function () {
    $s3 = fakeS3(new Result, s3Error('NotFound', 404));

    $this->actingAs(superadmin())
        ->postJson(route('storages.test-connection'), connectionPayload(['buckets' => "photos\nmissing"]))
        ->assertJson(['ok' => false])
        ->assertJsonPath('message', 'Бакет missing: Бакет не существует. (NotFound)');

    expect($s3->handler->getLastCommand()->getName())->toBe('HeadBucket');
});

it('translates storage errors', function (string $code, int $status, string $message) {
    fakeS3(s3Error($code, $status));

    $this->actingAs(superadmin())
        ->postJson(route('storages.test-connection'), connectionPayload())
        ->assertJson(['ok' => false])
        ->assertJsonPath('message', $message);
})->with([
    ['InvalidAccessKeyId', 403, 'Такой access key не существует. (InvalidAccessKeyId)'],
    ['SignatureDoesNotMatch', 403, 'Неверный secret key. (SignatureDoesNotMatch)'],
    ['AccessDenied', 403, 'Доступ запрещён. Проверьте права ключа. (AccessDenied)'],
]);

it('uses stored keys when testing an existing storage with empty keys', function () {
    $storage = Storage::factory()->create(['access_key_id' => 'STOREDKEY1', 'secret_access_key' => 'stored-secret']);
    $s3 = fakeS3(new Result(['Buckets' => []]));

    $this->actingAs(superadmin())
        ->postJson(route('storages.test-connection'), connectionPayload([
            'storage_id' => $storage->id,
            'access_key_id' => '',
            'secret_access_key' => '',
        ]))
        ->assertJson(['ok' => true]);

    expect($s3->connections[0])
        ->accessKeyId->toBe('STOREDKEY1')
        ->secretAccessKey->toBe('stored-secret');
});

it('returns validation errors as JSON', function () {
    $this->actingAs(superadmin())
        ->postJson(route('storages.test-connection'), connectionPayload(['region' => '']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('region');
});

it('blocks internal endpoints when private endpoints are disallowed', function () {
    app()->instance(EndpointGuard::class, new EndpointGuard(allowPrivate: false));
    fakeS3(new Result(['Buckets' => []]));

    $this->actingAs(superadmin())
        ->postJson(route('storages.test-connection'), connectionPayload(['endpoint' => 'http://127.0.0.1:9000']))
        ->assertJson(['ok' => false, 'message' => 'Endpoint во внутренней сети запрещён.']);

    $this->post(route('storages.store'), [...connectionPayload(['endpoint' => 'http://10.0.0.5']), 'name' => 'Internal'])
        ->assertInertiaFlash('toast.type', 'error');

    expect(Storage::query()->count())->toBe(0);
});
