<?php

use App\Domain\Enum\Permission\SystemPermission;
use App\Models\User;
use App\Services\Storage\EndpointGuard;
use App\Services\Storage\S3ClientFactory;
use Aws\MockHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeS3ClientFactory;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature', 'Integration');

require_once __DIR__.'/Support/helpers.php';

function superadmin(): User
{
    return User::factory()->superadmin()->create();
}

function userWith(SystemPermission ...$permissions): User
{
    return User::factory()->withPermissions(...$permissions)->create();
}

/**
 * Подменяет S3-клиент: ответы задаются по порядку (Aws\Result, исключения или callable).
 */
function fakeS3(mixed ...$responses): FakeS3ClientFactory
{
    $factory = new FakeS3ClientFactory(new MockHandler($responses), app(EndpointGuard::class));
    app()->instance(S3ClientFactory::class, $factory);

    return $factory;
}
