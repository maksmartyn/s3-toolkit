<?php

use App\Domain\Enum\Permission\PermissionScope;
use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Permission\SystemPermission;

it('keeps storage permission bits stable', function () {
    // Номера битов хранятся в БД: менять их нельзя
    expect(array_map(fn (StoragePermission $p) => [$p->name, $p->bit()], StoragePermission::cases()))->toBe([
        ['ViewBuckets', 0],
        ['CreateBuckets', 1],
        ['DeleteBuckets', 2],
        ['ManageAccessKeys', 3],
        ['ManageBucketPolicy', 4],
        ['ManageCors', 5],
        ['ManageLifecycle', 6],
        ['ManageVersioning', 7],
        ['ManageBucketTags', 8],
        ['ManageAcl', 9],
        ['ManageWebsite', 10],
        ['ManageObjectLock', 11],
        ['ViewObjects', 12],
        ['PutObjects', 13],
        ['DeleteObjects', 14],
        ['SharePresignedLinks', 15],
    ]);
});

it('maps scopes to permission enums', function () {
    expect(PermissionScope::System->enum())->toBe(SystemPermission::class)
        ->and(PermissionScope::Storage->enum())->toBe(StoragePermission::class);
});
