<?php

use App\Domain\Enum\Operation\OperationStatus;
use App\Domain\Enum\Operation\OperationType;
use App\Domain\Enum\Permission\StoragePermission;
use App\Models\BackgroundOperation;
use App\Models\BucketStats;
use App\Models\Storage;
use Aws\Result;
use Inertia\Testing\AssertableInertia as Assert;

it('loads buckets with stats and active operations as a deferred prop', function () {
    $storage = Storage::factory()->create();
    $user = storageUser($storage, StoragePermission::ViewBuckets);

    BucketStats::query()->create([
        'storage_id' => $storage->id, 'bucket' => 'alpha', 'object_count' => 3, 'total_size' => 2048, 'calculated_at' => now(),
    ]);
    BackgroundOperation::query()->create([
        'storage_id' => $storage->id, 'type' => OperationType::BucketStats, 'bucket' => 'beta', 'status' => OperationStatus::Running, 'processed' => 7,
    ]);

    fakeS3(new Result(['Buckets' => [
        ['Name' => 'beta', 'CreationDate' => new DateTimeImmutable('2026-01-02')],
        ['Name' => 'alpha', 'CreationDate' => new DateTimeImmutable('2026-01-01')],
    ]]));

    $this->actingAs($user)
        ->get(route('storages.show', $storage))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('buckets')
            ->where('can.viewBuckets', true)
            ->where('can.createBuckets', false)
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('buckets.error', null)
                ->where('buckets.buckets.0.name', 'alpha')
                ->where('buckets.buckets.0.stats.objectCount', 3)
                ->where('buckets.buckets.0.stats.totalSize', 2048)
                ->where('buckets.buckets.1.name', 'beta')
                ->where('buckets.buckets.1.stats', null)
                ->where('buckets.buckets.1.operations.0.processed', 7)));
});

it('shows a storage error instead of failing the page', function () {
    $storage = Storage::factory()->create();
    fakeS3(awsError('InvalidAccessKeyId', 403));

    $this->actingAs(superadmin())
        ->get(route('storages.show', $storage))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload
            ->where('buckets.buckets', [])
            ->where('buckets.error', 'Такой access key не существует. (InvalidAccessKeyId)')));
});

it('uses the configured bucket list without calling ListBuckets', function () {
    $storage = Storage::factory()->create(['buckets' => ['only-this']]);
    $s3 = fakeS3();

    $this->actingAs(superadmin())
        ->get(route('storages.show', $storage))
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload
            ->where('buckets.buckets.0.name', 'only-this')
            ->where('buckets.buckets.0.createdAt', null)));

    expect($s3->handler->getLastCommand())->toBeNull();
});

it('does not load buckets without ViewBuckets', function () {
    $storage = Storage::factory()->create();
    $user = storageUser($storage, StoragePermission::ViewObjects);
    $s3 = fakeS3();

    $this->actingAs($user)
        ->get(route('storages.show', $storage))
        ->assertInertia(fn (Assert $page) => $page->where('buckets', null)->where('can.viewBuckets', false));

    expect($s3->handler->getLastCommand())->toBeNull();
});

it('exposes capabilities and saves overrides from the storage form', function () {
    $this->actingAs(superadmin())->post(route('storages.store'), [
        'name' => 'R2', 'provider' => 'cloudflare_r2', 'endpoint' => 'https://abc.r2.cloudflarestorage.com', 'region' => 'auto',
        'access_key_id' => 'KEY12345', 'secret_access_key' => 'secret',
        'capabilities' => ['cors', 'lifecycle', 'multipart_uploads', 'tagging'],
    ])->assertSessionHasNoErrors();

    $storage = Storage::query()->firstOrFail();

    expect($storage->capabilities)->toBe(['tagging' => true]);

    fakeS3(new Result(['Buckets' => []]));

    $this->get(route('storages.show', $storage))
        ->assertInertia(fn (Assert $page) => $page->where('storage.capabilities', ['cors', 'lifecycle', 'tagging', 'multipart_uploads']));
});
