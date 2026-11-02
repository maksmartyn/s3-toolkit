<?php

use App\Exceptions\DomainException;
use App\Models\Storage;
use App\Services\Bucket\BucketService;
use Aws\S3\Exception\S3Exception;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Exceptions;

it('reports suppressed exceptions only in debug mode', function (bool $debug) {
    config(['app.debug' => $debug]);
    Exceptions::fake();
    fakeS3(awsError('AccessDenied', 403));

    $list = app(BucketService::class)->list(Storage::factory()->create()->id);

    expect($list->error)->not->toBeNull();

    $debug
        ? Exceptions::assertReported(S3Exception::class)
        : Exceptions::assertNothingReported();
})->with([true, false]);

it('reports domain exceptions only in debug mode', function () {
    $handler = app(ExceptionHandler::class);

    config(['app.debug' => false]);
    expect($handler->shouldReport(new DomainException('x')))->toBeFalse();

    config(['app.debug' => true]);
    expect($handler->shouldReport(new DomainException('x')))->toBeTrue();
});
