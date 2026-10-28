<?php

use App\Domain\Enum\Audit\AuditAction;
use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Storage\StorageProvider;
use App\Models\AuditLogEntry;
use App\Models\Storage;
use Aws\Result;

it('requires the CreateBuckets permission', function () {
    $storage = Storage::factory()->create();

    $this->actingAs(storageUser($storage, StoragePermission::ViewBuckets))
        ->post(route('storages.buckets.store', $storage), ['name' => 'new-bucket'])
        ->assertForbidden();
});

it('validates bucket names', function (string $name) {
    $storage = Storage::factory()->create();

    $this->actingAs(superadmin())
        ->post(route('storages.buckets.store', $storage), ['name' => $name])
        ->assertSessionHasErrors('name');
})->with(['ab', 'UPPER', 'has_underscore', '-start', 'end-', 'two..dots', '192.168.1.1', str_repeat('a', 64)]);

it('creates a bucket and records it', function () {
    $storage = Storage::factory()->create();
    $s3 = fakeS3(new Result);

    $this->actingAs(storageUser($storage, StoragePermission::CreateBuckets))
        ->post(route('storages.buckets.store', $storage), ['name' => 'my.new-bucket'])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success');

    $command = $s3->handler->getLastCommand();

    expect($command->getName())->toBe('CreateBucket')
        ->and($command['Bucket'])->toBe('my.new-bucket')
        ->and($command['CreateBucketConfiguration'] ?? null)->toBeNull()
        ->and($command['ObjectLockEnabledForBucket'] ?? null)->toBeNull()
        ->and(AuditLogEntry::query()->firstOrFail())
        ->action->toBe(AuditAction::BucketCreated)
        ->subject_label->toBe('my.new-bucket');
});

it('sends LocationConstraint only for AWS outside us-east-1', function (string $region, ?string $expected) {
    $storage = Storage::factory()->create(['provider' => StorageProvider::Aws, 'endpoint' => null, 'region' => $region]);
    $s3 = fakeS3(new Result);

    $this->actingAs(superadmin())->post(route('storages.buckets.store', $storage), ['name' => 'aws-bucket']);

    expect($s3->handler->getLastCommand()['CreateBucketConfiguration']['LocationConstraint'] ?? null)->toBe($expected);
})->with([['us-east-1', null], ['eu-central-1', 'eu-central-1']]);

it('enables Object Lock only where supported', function () {
    $minio = Storage::factory()->create();
    $r2 = Storage::factory()->create(['provider' => StorageProvider::CloudflareR2]);
    $s3 = fakeS3(new Result);

    $this->actingAs(superadmin());

    $this->post(route('storages.buckets.store', $minio), ['name' => 'locked', 'object_lock' => true])->assertSessionHasNoErrors();
    expect($s3->handler->getLastCommand()['ObjectLockEnabledForBucket'])->toBeTrue();

    $this->post(route('storages.buckets.store', $r2), ['name' => 'locked', 'object_lock' => true])
        ->assertInertiaFlash('toast.message', 'Это хранилище не поддерживает Object Lock.');
});

it('reports storage errors and appends to the configured bucket list', function () {
    $storage = Storage::factory()->create(['buckets' => ['existing']]);
    fakeS3(awsError('BucketAlreadyExists', 409), new Result);

    $this->actingAs(superadmin());

    $this->post(route('storages.buckets.store', $storage), ['name' => 'taken'])
        ->assertInertiaFlash('toast.type', 'error');

    $this->post(route('storages.buckets.store', $storage), ['name' => 'fresh'])->assertSessionHasNoErrors();

    expect($storage->refresh()->buckets)->toBe(['existing', 'fresh']);
});
