<?php

use App\Domain\Enum\Audit\AuditAction;
use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Permission\SystemPermission;
use App\Models\AuditLogEntry;
use App\Models\Storage;
use App\Models\StorageAccess;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->storage = Storage::factory()->create();
    $this->granter = userWith(SystemPermission::GrantStoragePermissions);
});

it('grants, changes and revokes storage permissions', function () {
    $user = User::factory()->create();
    $this->actingAs($this->granter);

    $this->put(route('storages.access.update', $this->storage), ['user_id' => $user->id, 'permissions' => [0, 12]])
        ->assertSessionHasNoErrors();
    expect(StorageAccess::query()->firstOrFail()->permissions->values())->toBe([0, 12]);

    $this->put(route('storages.access.update', $this->storage), ['user_id' => $user->id, 'permissions' => [0, 12, 13]]);
    expect(StorageAccess::query()->firstOrFail())
        ->permissions->values()->toBe([0, 12, 13])
        ->granted_by->toBe($this->granter->id);

    $this->put(route('storages.access.update', $this->storage), ['user_id' => $user->id, 'permissions' => []]);
    expect(StorageAccess::query()->count())->toBe(0);

    $entries = AuditLogEntry::query()->orderBy('id')->get();
    expect($entries)->toHaveCount(3)
        ->and($entries->pluck('action')->unique()->all())->toBe([AuditAction::StorageAccessChanged])
        ->and($entries[0]->details['user']['email'])->toBe($user->email)
        ->and($entries[2]->details['permissions']['revoked'])->toBe(['ViewBuckets', 'ViewObjects', 'PutObjects']);
});

it('shows grants and candidates to granters', function () {
    $user = User::factory()->create(['name' => 'Bob']);
    User::factory()->superadmin()->create();
    $deleted = User::factory()->create();
    $deleted->delete();

    $this->actingAs($this->granter)
        ->put(route('storages.access.update', $this->storage), ['user_id' => $user->id, 'permissions' => [0]]);

    $this->get(route('storages.show', $this->storage))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manageAccess', true)
            ->has('accesses', 1)
            ->where('accesses.0.userName', 'Bob')
            ->has('candidates', 1)
            ->where('candidates.0.id', $user->id)
            ->has('permissionOptions', count(StoragePermission::cases())));
});

it('forbids granting to self, to superadmins and without permission', function () {
    $this->actingAs($this->granter)
        ->put(route('storages.access.update', $this->storage), ['user_id' => $this->granter->id, 'permissions' => [0]])
        ->assertInertiaFlash('toast.type', 'error');

    $this->put(route('storages.access.update', $this->storage), ['user_id' => superadmin()->id, 'permissions' => [0]])
        ->assertInertiaFlash('toast.type', 'error');

    $this->actingAs(userWith(SystemPermission::ManageStorages))
        ->put(route('storages.access.update', $this->storage), ['user_id' => User::factory()->create()->id, 'permissions' => [0]])
        ->assertForbidden();

    expect(StorageAccess::query()->count())->toBe(0);
});

it('validates the target user and permissions', function () {
    $deleted = User::factory()->create();
    $deleted->delete();

    $this->actingAs($this->granter);

    $this->put(route('storages.access.update', $this->storage), ['user_id' => $deleted->id, 'permissions' => [0]])
        ->assertSessionHasErrors('user_id');
    $this->put(route('storages.access.update', $this->storage), ['user_id' => User::factory()->create()->id, 'permissions' => [16]])
        ->assertSessionHasErrors('permissions.0');
});
