<?php

use App\Domain\Enum\Audit\AuditAction;
use App\Domain\Enum\Operation\OperationStatus;
use App\Domain\Enum\Operation\OperationType;
use App\Domain\Enum\Permission\StoragePermission;
use App\Jobs\Bucket\CalculateBucketStatsJob;
use App\Jobs\Bucket\DeleteBucketJob;
use App\Models\AuditLogEntry;
use App\Models\BackgroundOperation;
use App\Models\BucketStats;
use App\Models\Storage;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => Queue::fake());

it('requires DeleteBuckets and the exact name', function () {
    $storage = Storage::factory()->create();

    $this->actingAs(storageUser($storage, StoragePermission::ViewBuckets, StoragePermission::DeleteObjects))
        ->delete(route('storages.buckets.destroy', [$storage, 'data']), ['confirmation' => 'data'])
        ->assertForbidden();

    $this->actingAs(storageUser($storage, StoragePermission::DeleteBuckets))
        ->delete(route('storages.buckets.destroy', [$storage, 'data']), ['confirmation' => 'Data'])
        ->assertSessionHasErrors('confirmation');

    Queue::assertNothingPushed();
});

it('starts a background deletion and records it right away', function () {
    $storage = Storage::factory()->create();
    BucketStats::query()->create([
        'storage_id' => $storage->id, 'bucket' => 'my.data', 'object_count' => 10, 'total_size' => 1, 'version_count' => 15, 'calculated_at' => now(),
    ]);
    $user = storageUser($storage, StoragePermission::DeleteBuckets);

    $this->actingAs($user)
        ->delete(route('storages.buckets.destroy', [$storage, 'my.data']), ['confirmation' => 'my.data'])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'info');

    $operation = BackgroundOperation::query()->firstOrFail();

    expect($operation)
        ->type->toBe(OperationType::BucketDelete)
        ->status->toBe(OperationStatus::Queued)
        ->user_id->toBe($user->id)
        ->total->toBe(15)
        ->and(AuditLogEntry::query()->firstOrFail())
        ->action->toBe(AuditAction::BucketDeletionStarted)
        ->actor_id->toBe($user->id);

    Queue::assertPushed(DeleteBucketJob::class, fn (DeleteBucketJob $job) => $job->operationId === $operation->id);
});

it('does not start a second operation on the same bucket', function () {
    $storage = Storage::factory()->create();
    $this->actingAs(superadmin());

    $this->post(route('storages.buckets.stats', [$storage, 'data']))->assertSessionHasNoErrors();
    $this->delete(route('storages.buckets.destroy', [$storage, 'data']), ['confirmation' => 'data'])
        ->assertInertiaFlash('toast.message', 'С этим бакетом уже выполняется другая операция.');

    expect(BackgroundOperation::query()->count())->toBe(1);
    Queue::assertPushed(CalculateBucketStatsJob::class);
    Queue::assertNotPushed(DeleteBucketJob::class);
});

it('rejects buckets outside the configured list', function () {
    $storage = Storage::factory()->create(['buckets' => ['allowed']]);

    $this->actingAs(superadmin())
        ->post(route('storages.buckets.stats', [$storage, 'other']))
        ->assertInertiaFlash('toast.type', 'error');

    Queue::assertNothingPushed();
});

it('requires ViewBuckets for statistics', function () {
    $storage = Storage::factory()->create();

    $this->actingAs(storageUser($storage, StoragePermission::ViewObjects))
        ->post(route('storages.buckets.stats', [$storage, 'data']))
        ->assertForbidden();
});
