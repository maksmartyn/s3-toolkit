<?php

use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Permission\SystemPermission;
use App\Domain\ValueObject\Permission\PermissionMask;
use App\Models\Storage;
use App\Models\StorageAccess;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function grantStorage(User $user, Storage $storage, StoragePermission ...$permissions): void
{
    StorageAccess::query()->create([
        'storage_id' => $storage->id,
        'user_id' => $user->id,
        'permissions' => PermissionMask::of(StoragePermission::class, $permissions),
    ]);
}

it('lists all storages for managers and granters', function (SystemPermission $permission) {
    Storage::factory()->count(2)->create();

    $this->actingAs(userWith($permission))
        ->get(route('storages.index'))
        ->assertInertia(fn (Assert $page) => $page->has('storages', 2)->where('auth.can.viewStorages', true));
})->with([SystemPermission::ManageStorages, SystemPermission::GrantStoragePermissions]);

it('lists only accessible storages with own permissions for regular users', function () {
    $user = User::factory()->create();
    [$mine, $other] = Storage::factory()->count(2)->create();
    grantStorage($user, $mine, StoragePermission::ViewBuckets, StoragePermission::ViewObjects);

    $this->actingAs($user)
        ->get(route('storages.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('storages', 1)
            ->where('storages.0.id', $mine->id)
            ->where('storages.0.permissions', [0, 12])
            ->where('can.create', false)
            ->where('auth.can.viewStorages', true));

    $this->get(route('storages.show', $other))->assertForbidden();
    $this->get(route('storages.show', $mine))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('details', null)
            ->where('accesses', [])
            ->where('can.manageAccess', false));
});

it('hides the storages menu item from users without any access', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.can.viewStorages', false));
});

it('gives superadmin all storage permissions', function () {
    $storage = Storage::factory()->create();

    $this->actingAs(superadmin())
        ->get(route('storages.show', $storage))
        ->assertInertia(fn (Assert $page) => $page
            ->has('storage.permissions', count(StoragePermission::cases()))
            ->whereNot('details', null));
});

it('does not give managers access to storage contents', function () {
    $storage = Storage::factory()->create();

    $this->actingAs(userWith(SystemPermission::ManageStorages))
        ->get(route('storages.show', $storage))
        ->assertInertia(fn (Assert $page) => $page
            ->where('storage.permissions', [])
            ->whereNot('details', null)
            ->where('can.update', true)
            ->where('can.manageAccess', false));
});
