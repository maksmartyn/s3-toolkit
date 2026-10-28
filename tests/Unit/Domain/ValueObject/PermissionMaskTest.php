<?php

use App\Domain\Enum\Permission\Contracts\BitPermission;
use App\Domain\Enum\Permission\SystemPermission;
use App\Domain\ValueObject\Permission\PermissionMask;

enum PermissionMaskTestOther: int implements BitPermission
{
    case Foo = 0;

    public function bit(): int
    {
        return $this->value;
    }

    public function label(): string
    {
        return 'foo';
    }
}

it('grants, revokes and checks permissions immutably', function () {
    $empty = PermissionMask::none(SystemPermission::class);
    $mask = $empty->grant(SystemPermission::ManageUsers)->grant(SystemPermission::ViewAuditLog);

    expect($empty->isEmpty())->toBeTrue()
        ->and($mask->value)->toBe((1 << 3) | (1 << 5))
        ->and($mask->has(SystemPermission::ManageUsers))->toBeTrue()
        ->and($mask->has(SystemPermission::ManageStorages))->toBeFalse()
        ->and($mask->revoke(SystemPermission::ManageUsers)->has(SystemPermission::ManageUsers))->toBeFalse()
        ->and($mask->has(SystemPermission::ManageUsers))->toBeTrue();
});

it('builds masks from permissions and values', function () {
    $mask = PermissionMask::fromValues(SystemPermission::class, [0, 3]);

    expect($mask->permissions())->toBe([SystemPermission::ManageStorages, SystemPermission::ManageUsers])
        ->and($mask->values())->toBe([0, 3])
        ->and(json_encode($mask))->toBe('[0,3]')
        ->and($mask->equals(PermissionMask::of(SystemPermission::class, [SystemPermission::ManageUsers, SystemPermission::ManageStorages])))->toBeTrue()
        ->and(PermissionMask::all(SystemPermission::class)->permissions())->toBe(SystemPermission::cases());
});

it('compares subsets', function () {
    $small = PermissionMask::of(SystemPermission::class, [SystemPermission::ManageUsers]);
    $big = $small->grant(SystemPermission::ManageStorages);

    expect($small->isSubsetOf($big))->toBeTrue()
        ->and($big->isSubsetOf($small))->toBeFalse()
        ->and(PermissionMask::none(SystemPermission::class)->isSubsetOf($small))->toBeTrue();
});

it('ignores unknown bits when listing permissions', function () {
    $mask = PermissionMask::fromInt(SystemPermission::class, (1 << 40) | 1);

    expect($mask->permissions())->toBe([SystemPermission::ManageStorages])
        ->and($mask->value)->toBe((1 << 40) | 1);
});

it('rejects permissions of another type', function () {
    PermissionMask::none(SystemPermission::class)->grant(PermissionMaskTestOther::Foo);
})->throws(InvalidArgumentException::class);

it('rejects negative masks', function () {
    PermissionMask::fromInt(SystemPermission::class, -1);
})->throws(InvalidArgumentException::class);

it('keeps system permission bits stable', function () {
    // Номера битов хранятся в БД: менять их нельзя
    expect(array_map(fn (SystemPermission $p) => [$p->name, $p->bit()], SystemPermission::cases()))->toBe([
        ['ManageStorages', 0],
        ['GrantSystemPermissions', 1],
        ['GrantStoragePermissions', 2],
        ['ManageUsers', 3],
        ['ManagePermissionTemplates', 4],
        ['ViewAuditLog', 5],
    ]);
});
