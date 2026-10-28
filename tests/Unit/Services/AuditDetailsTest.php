<?php

use App\Domain\Enum\Permission\PermissionScope;
use App\Domain\Enum\Permission\SystemPermission;
use App\Domain\ValueObject\Permission\PermissionMask;
use App\Services\Audit\AuditDetails;

it('keeps only changed fields', function () {
    expect(AuditDetails::changes(['name' => 'A', 'email' => 'a@x'], ['name' => 'B', 'email' => 'a@x']))
        ->toBe(['changes' => ['name' => ['from' => 'A', 'to' => 'B']]])
        ->and(AuditDetails::changes(['name' => 'A'], ['name' => 'A']))->toBe([]);
});

it('describes granted and revoked permissions by enum names', function () {
    $before = PermissionMask::of(SystemPermission::class, [SystemPermission::ManageUsers, SystemPermission::ViewAuditLog]);
    $after = PermissionMask::of(SystemPermission::class, [SystemPermission::ManageUsers, SystemPermission::ManageStorages]);

    expect(AuditDetails::permissions(PermissionScope::System, $before, $after))->toBe(['permissions' => [
        'scope' => 'system',
        'granted' => ['ManageStorages'],
        'revoked' => ['ViewAuditLog'],
    ]]);
});
