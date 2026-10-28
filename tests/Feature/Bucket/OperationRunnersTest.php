<?php

use App\Domain\Enum\Audit\AuditAction;
use App\Domain\Enum\Operation\OperationStatus;
use App\Domain\Enum\Operation\OperationType;
use App\Domain\Enum\Storage\StorageProvider;
use App\Jobs\Bucket\CalculateBucketStatsJob;
use App\Jobs\Bucket\DeleteBucketJob;
use App\Models\AuditLogEntry;
use App\Models\BackgroundOperation;
use App\Models\BucketStats;
use App\Models\Storage;
use App\Models\User;
use App\Services\Bucket\BucketDeletionRunner;
use App\Services\Bucket\BucketStatsRunner;
use Aws\Result;
use Illuminate\Support\Facades\Queue;

function operation(Storage $storage, OperationType $type, string $bucket = 'data', ?User $user = null): BackgroundOperation
{
    return BackgroundOperation::query()->create([
        'storage_id' => $storage->id,
        'user_id' => $user?->id,
        'type' => $type,
        'bucket' => $bucket,
        'status' => OperationStatus::Queued,
    ]);
}

it('deletes versions, aborts uploads and removes the bucket', function () {
    $user = User::factory()->create();
    $storage = Storage::factory()->create(['buckets' => ['data', 'keep']]);
    BucketStats::query()->create(['storage_id' => $storage->id, 'bucket' => 'data', 'object_count' => 1, 'total_size' => 1, 'calculated_at' => now()]);
    $operation = operation($storage, OperationType::BucketDelete, user: $user);

    $s3 = fakeS3(
        new Result(['Versions' => [['Key' => 'a', 'VersionId' => 'v1'], ['Key' => 'a', 'VersionId' => 'v2']], 'DeleteMarkers' => [['Key' => 'b', 'VersionId' => 'm1']]]),
        new Result([]),
        new Result([]),
        new Result(['Uploads' => [['Key' => 'big', 'UploadId' => 'u1']]]),
        new Result([]),
        new Result([]),
        new Result([]),
    );

    expect(app(BucketDeletionRunner::class)->run($operation->id, 10))->toBeTrue();

    expect($s3->handler->count())->toBe(0)
        ->and($operation->refresh())
        ->status->toBe(OperationStatus::Completed)
        ->processed->toBe(3)
        ->and(BucketStats::query()->count())->toBe(0)
        ->and($storage->refresh()->buckets)->toBe(['keep'])
        ->and(AuditLogEntry::query()->firstOrFail())
        ->action->toBe(AuditAction::BucketDeleted)
        ->actor_id->toBe($user->id)
        ->details->toMatchArray(['values' => ['deleted_objects' => 3]]);
});

it('deletes plain objects when versioning is not supported', function () {
    $storage = Storage::factory()->create(['provider' => StorageProvider::CloudflareR2]);
    $operation = operation($storage, OperationType::BucketDelete);

    $s3 = fakeS3(
        new Result(['Contents' => [['Key' => 'x', 'Size' => 1]]]),
        function ($command) {
            expect($command->getName())->toBe('DeleteObjects')
                ->and($command['Delete']['Objects'])->toBe([['Key' => 'x']]);

            return new Result([]);
        },
        new Result([]),
        new Result([]),
        new Result([]),
    );

    app(BucketDeletionRunner::class)->run($operation->id, 10);

    expect($operation->refresh()->status)->toBe(OperationStatus::Completed)
        ->and($s3->handler->count())->toBe(0);
});

it('falls back to plain objects when ListObjectVersions is not implemented', function () {
    $storage = Storage::factory()->create(['provider' => StorageProvider::Generic]);
    $operation = operation($storage, OperationType::BucketDelete);

    fakeS3(
        awsError('NotImplemented', 501), // ListObjectVersions
        new Result(['Contents' => [['Key' => 'x']]]),
        new Result([]), // DeleteObjects
        new Result([]), // ListObjectsV2 — пусто
        awsError('NotImplemented', 501), // ListMultipartUploads
        new Result([]), // DeleteBucket
    );

    app(BucketDeletionRunner::class)->run($operation->id, 10);

    expect($operation->refresh())->status->toBe(OperationStatus::Completed)->processed->toBe(1);
});

it('fails when objects cannot be deleted', function () {
    $storage = Storage::factory()->create();
    $operation = operation($storage, OperationType::BucketDelete);

    fakeS3(
        new Result(['Versions' => [['Key' => 'locked', 'VersionId' => 'v1']]]),
        new Result(['Errors' => [['Key' => 'locked', 'Code' => 'AccessDenied', 'Message' => 'Object is WORM protected']]]),
    );

    expect(app(BucketDeletionRunner::class)->run($operation->id, 10))->toBeTrue();

    expect($operation->refresh())
        ->status->toBe(OperationStatus::Failed)
        ->error->toContain('locked')->toContain('WORM');
});

it('marks the operation failed on storage errors', function () {
    $storage = Storage::factory()->create();
    $operation = operation($storage, OperationType::BucketDelete);
    fakeS3(awsError('NoSuchBucket', 404), awsError('NoSuchBucket', 404));

    app(BucketDeletionRunner::class)->run($operation->id, 10);

    expect($operation->refresh())->status->toBe(OperationStatus::Failed)->error->toContain('NoSuchBucket');
});

it('continues in a new job when the step time is over', function () {
    Queue::fake();
    config(['s3toolkit.operations.step_seconds' => 0]);
    $storage = Storage::factory()->create();
    $operation = operation($storage, OperationType::BucketDelete);
    fakeS3();

    (new DeleteBucketJob($operation->id))->handle();

    expect($operation->refresh()->status)->toBe(OperationStatus::Running);
    Queue::assertPushed(DeleteBucketJob::class, fn (DeleteBucketJob $job) => $job->operationId === $operation->id);
});

it('ignores finished or removed operations', function () {
    Queue::fake();
    $storage = Storage::factory()->create();
    $operation = operation($storage, OperationType::BucketStats);
    $operation->forceFill(['status' => OperationStatus::Completed])->save();

    (new CalculateBucketStatsJob($operation->id))->handle();
    (new CalculateBucketStatsJob(999_999))->handle();

    Queue::assertNothingPushed();
});

it('counts objects and all versions across pages', function () {
    $storage = Storage::factory()->create();
    $operation = operation($storage, OperationType::BucketStats);

    $s3 = fakeS3(
        new Result([
            'IsTruncated' => true, 'NextKeyMarker' => 'b', 'NextVersionIdMarker' => 'v9',
            'Versions' => [
                ['Key' => 'a', 'VersionId' => 'v1', 'Size' => 100, 'IsLatest' => true],
                ['Key' => 'a', 'VersionId' => 'v0', 'Size' => 50, 'IsLatest' => false],
            ],
        ]),
        new Result([
            'Versions' => [['Key' => 'b', 'VersionId' => 'v2', 'Size' => 10, 'IsLatest' => false]],
            'DeleteMarkers' => [['Key' => 'b', 'VersionId' => 'm1', 'IsLatest' => true]],
        ]),
    );

    app(BucketStatsRunner::class)->run($operation->id, 10);

    expect($s3->handler->getLastCommand()['KeyMarker'])->toBe('b')
        ->and(BucketStats::query()->firstOrFail())
        ->object_count->toBe(1)
        ->total_size->toBe(100)
        ->version_count->toBe(4)
        ->versions_size->toBe(160)
        ->and($operation->refresh()->status)->toBe(OperationStatus::Completed);
});

it('counts current objects when versioning is not supported', function () {
    $storage = Storage::factory()->create(['provider' => StorageProvider::CloudflareR2]);
    $operation = operation($storage, OperationType::BucketStats);

    fakeS3(
        new Result(['IsTruncated' => true, 'NextContinuationToken' => 't1', 'Contents' => [['Key' => 'a', 'Size' => 5]]]),
        new Result(['Contents' => [['Key' => 'b', 'Size' => 7], ['Key' => 'c', 'Size' => 0]]]),
    );

    app(BucketStatsRunner::class)->run($operation->id, 10);

    expect(BucketStats::query()->firstOrFail())
        ->object_count->toBe(3)
        ->total_size->toBe(12)
        ->version_count->toBeNull();
});
