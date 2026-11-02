<?php

use App\Domain\Enum\Operation\OperationStatus;
use App\Domain\Enum\Operation\OperationType;
use App\Jobs\Bucket\CalculateBucketStatsJob;
use App\Models\BackgroundOperation;
use App\Models\Storage;
use App\Services\Operation\OperationService;
use App\Services\Storage\S3ClientFactory;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Queue;

function statsOperation(Storage $storage, array $state = []): BackgroundOperation
{
    return BackgroundOperation::query()->create([
        'storage_id' => $storage->id,
        'type' => OperationType::BucketStats,
        'bucket' => 'big',
        'status' => OperationStatus::Running,
        'state' => $state === [] ? null : $state,
    ]);
}

it('keeps the job timeout above the slowest step and below retry_after', function () {
    $step = (float) config('s3toolkit.operations.step_seconds');
    $request = (float) config('s3toolkit.storage.connect_timeout') + (float) config('s3toolkit.storage.timeout');
    $slowestStep = $step + (S3ClientFactory::RETRIES + 1) * $request;
    $timeout = (int) config('s3toolkit.operations.job_timeout');

    expect($timeout)->toBeGreaterThan($slowestStep)
        ->and($timeout)->toBeLessThan((int) config('queue.connections.redis.retry_after'))
        ->and((new CalculateBucketStatsJob(1))->timeout)->toBe($timeout);
});

it('continues an operation after a step timed out, keeping the saved progress', function () {
    Queue::fake();
    $operation = statsOperation(Storage::factory()->create(), ['objects' => 5000, 'token' => 'page-6']);

    (new CalculateBucketStatsJob($operation->id))->failed(new TimeoutExceededException('timed out'));

    Queue::assertPushed(CalculateBucketStatsJob::class, fn ($job) => $job->operationId === $operation->id);
    expect($operation->refresh())
        ->status->toBe(OperationStatus::Running)
        ->state->toBe(['objects' => 5000, 'token' => 'page-6', 'timeouts' => 1]);

    // Шаг продвинулся — счётчик обнуляется
    app(OperationService::class)->progress($operation->id, 6000, [...$operation->state, 'token' => 'page-7']);
    expect($operation->refresh()->state)->not->toHaveKey('timeouts');
});

it('fails the operation after too many timeouts in a row', function () {
    Queue::fake();
    $operation = statsOperation(Storage::factory()->create(), ['timeouts' => 3]);

    (new CalculateBucketStatsJob($operation->id))->failed(new TimeoutExceededException('timed out'));

    Queue::assertNothingPushed();
    expect($operation->refresh())
        ->status->toBe(OperationStatus::Failed)
        ->error->toContain('слишком медленно');
});

it('marks operations without progress as interrupted so they no longer block the bucket', function () {
    Queue::fake();
    $storage = Storage::factory()->create();
    $stale = statsOperation($storage);
    BackgroundOperation::query()->whereKey($stale->id)->update(['updated_at' => now()->subHour()]);

    expect(app(OperationService::class)->activeFor($storage->id))->toBe([])
        ->and($stale->refresh()->status)->toBe(OperationStatus::Failed);

    app(OperationService::class)->start(null, $storage->id, OperationType::BucketStats, 'big');
    Queue::assertPushed(CalculateBucketStatsJob::class);
});
