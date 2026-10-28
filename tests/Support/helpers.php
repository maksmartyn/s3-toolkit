<?php

use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\ValueObject\Permission\PermissionMask;
use App\Models\Storage;
use App\Models\StorageAccess;
use App\Models\User;
use Aws\Command;
use Aws\S3\Exception\S3Exception;
use GuzzleHttp\Psr7\Response;

function storageUser(Storage $storage, StoragePermission ...$permissions): User
{
    $user = User::factory()->create();

    StorageAccess::query()->create([
        'storage_id' => $storage->id,
        'user_id' => $user->id,
        'permissions' => PermissionMask::of(StoragePermission::class, $permissions),
    ]);

    return $user;
}

function awsError(string $code, int $status = 400): Closure
{
    return fn (Command $command) => new S3Exception($code, $command, ['code' => $code, 'response' => new Response($status)]);
}
