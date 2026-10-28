<?php

use App\Domain\Enum\Audit\AuditAction;
use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Permission\SystemPermission;
use App\Domain\Enum\Storage\StorageProvider;
use App\Domain\ValueObject\Permission\PermissionMask;
use App\Models\AuditLogEntry;
use App\Models\Storage;
use App\Models\StorageAccess;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function storagePayload(array $overrides = []): array
{
    return [
        'name' => 'Main MinIO',
        'provider' => 'minio',
        'endpoint' => 'http://minio:9000',
        'region' => 'us-east-1',
        'use_path_style' => true,
        'verify_ssl' => true,
        'access_key_id' => 'AKIAEXAMPLE1234',
        'secret_access_key' => 'super-secret-value',
        ...$overrides,
    ];
}

it('forbids storage management without ManageStorages', function () {
    $storage = Storage::factory()->create();

    $this->actingAs(userWith(SystemPermission::GrantStoragePermissions));

    $this->get(route('storages.create'))->assertForbidden();
    $this->post(route('storages.store'), storagePayload())->assertForbidden();
    $this->get(route('storages.edit', $storage))->assertForbidden();
    $this->put(route('storages.update', $storage), storagePayload())->assertForbidden();
    $this->delete(route('storages.destroy', $storage))->assertForbidden();
    $this->postJson(route('storages.test-connection'), storagePayload())->assertForbidden();
});

it('creates a storage with encrypted keys and no automatic access for the author', function () {
    $manager = userWith(SystemPermission::ManageStorages);

    $this->actingAs($manager)->post(route('storages.store'), storagePayload(['buckets' => "photos\n\n docs "]))
        ->assertSessionHasNoErrors();

    $storage = Storage::query()->firstOrFail();
    $raw = DB::table('storages')->first();

    expect($storage)
        ->provider->toBe(StorageProvider::Minio)
        ->access_key_id->toBe('AKIAEXAMPLE1234')
        ->secret_access_key->toBe('super-secret-value')
        ->buckets->toBe(['photos', 'docs'])
        ->created_by->toBe($manager->id)
        ->and($raw->secret_access_key)->not->toContain('super-secret-value')
        ->and($raw->access_key_id)->not->toContain('AKIAEXAMPLE1234')
        ->and(StorageAccess::query()->count())->toBe(0);

    $entry = AuditLogEntry::query()->firstOrFail();
    expect($entry->action)->toBe(AuditAction::StorageCreated)
        ->and(json_encode($entry->details))->not->toContain('super-secret-value')->not->toContain('AKIAEXAMPLE1234');
});

it('validates the form', function (array $overrides, string $field) {
    Storage::factory()->create(['name' => 'Taken']);

    $this->actingAs(superadmin())
        ->post(route('storages.store'), storagePayload($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'duplicate name' => [['name' => 'Taken'], 'name'],
    'unknown provider' => [['provider' => 'ftp'], 'provider'],
    'endpoint required for MinIO' => [['endpoint' => ''], 'endpoint'],
    'endpoint must be http(s)' => [['endpoint' => 'ftp://host'], 'endpoint'],
    'R2 placeholder not filled' => [['provider' => 'cloudflare_r2', 'endpoint' => 'https://{accountId}.r2.cloudflarestorage.com'], 'endpoint'],
    'missing secret' => [['secret_access_key' => ''], 'secret_access_key'],
    'bucket with slash' => [['buckets' => ['a/b']], 'buckets.0'],
]);

it('does not require an endpoint for AWS', function () {
    $this->actingAs(superadmin())
        ->post(route('storages.store'), storagePayload(['provider' => 'aws', 'endpoint' => '']))
        ->assertSessionHasNoErrors();

    expect(Storage::query()->firstOrFail()->endpoint)->toBeNull();
});

it('updates settings and keeps keys when they are left empty', function () {
    $storage = Storage::factory()->create(['secret_access_key' => 'old-secret', 'access_key_id' => 'OLDKEY12345']);

    $this->actingAs(superadmin())
        ->put(route('storages.update', $storage), storagePayload(['name' => 'Renamed', 'region' => 'eu-1', 'access_key_id' => '', 'secret_access_key' => '']))
        ->assertSessionHasNoErrors();

    expect($storage->refresh())
        ->name->toBe('Renamed')
        ->region->toBe('eu-1')
        ->secret_access_key->toBe('old-secret')
        ->access_key_id->toBe('OLDKEY12345');

    $entry = AuditLogEntry::query()->where('action', AuditAction::StorageUpdated)->firstOrFail();
    expect($entry->details['changes'])->toHaveKeys(['name', 'region'])
        ->and($entry->details)->not->toHaveKey('flags');
});

it('replaces keys only as a pair', function () {
    $storage = Storage::factory()->create();

    $this->actingAs(superadmin());

    $this->put(route('storages.update', $storage), storagePayload(['access_key_id' => 'NEWKEY', 'secret_access_key' => '']))
        ->assertSessionHasErrors('secret_access_key');

    $this->put(route('storages.update', $storage), storagePayload(['access_key_id' => 'NEWKEY12345', 'secret_access_key' => 'new-secret']))
        ->assertSessionHasNoErrors();

    expect($storage->refresh()->secret_access_key)->toBe('new-secret')
        ->and(AuditLogEntry::query()->latest('id')->firstOrFail()->details['flags'])->toBe(['credentialsChanged' => true]);
});

it('deletes a storage with its permissions', function () {
    $storage = Storage::factory()->create();
    StorageAccess::query()->create([
        'storage_id' => $storage->id,
        'user_id' => User::factory()->create()->id,
        'permissions' => PermissionMask::all(StoragePermission::class),
    ]);

    $this->actingAs(superadmin())
        ->delete(route('storages.destroy', $storage))
        ->assertRedirect(route('storages.index'));

    expect(Storage::query()->count())->toBe(0)
        ->and(StorageAccess::query()->count())->toBe(0)
        ->and(AuditLogEntry::query()->latest('id')->firstOrFail())
        ->action->toBe(AuditAction::StorageDeleted)
        ->subject_label->toBe($storage->name);
});

it('never sends keys to the browser', function () {
    $storage = Storage::factory()->create(['access_key_id' => 'AKIAVISIBLEKEY99', 'secret_access_key' => 'hidden-secret']);

    $this->actingAs(superadmin());

    foreach ([route('storages.show', $storage), route('storages.edit', $storage), route('storages.index')] as $url) {
        $this->get($url)->assertOk()->assertDontSee('hidden-secret')->assertDontSee('AKIAVISIBLEKEY99');
    }

    $this->get(route('storages.show', $storage))
        ->assertInertia(fn (Assert $page) => $page->where('details.accessKeyIdMasked', 'AKIA…EY99'));
});

it('offers provider presets on the form', function () {
    $this->actingAs(userWith(SystemPermission::ManageStorages))
        ->get(route('storages.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('storages/Form')
            ->has('providers', count(StorageProvider::cases()))
            ->where('providers.1.value', 'yandex')
            ->where('providers.1.defaultEndpoint', 'https://storage.yandexcloud.net')
            ->where('providers.1.defaultRegion', 'ru-central1'));
});
