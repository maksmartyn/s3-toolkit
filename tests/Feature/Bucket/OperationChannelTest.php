<?php

use App\Domain\Enum\Permission\StoragePermission;
use App\Models\Storage;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

beforeEach(function () {
    // В тестах вещание выключено (null) — для проверки авторизации каналов включаем Reverb с тестовыми ключами
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);
    Broadcast::forgetDrivers();
    require base_path('routes/channels.php');
});

function authorizeChannel(string $channel)
{
    return test()->post('/broadcasting/auth', ['channel_name' => 'private-'.$channel, 'socket_id' => '1234.5678']);
}

it('allows the storage channel to users who can view the storage', function () {
    $storage = Storage::factory()->create();

    $this->actingAs(storageUser($storage, StoragePermission::ViewBuckets));

    authorizeChannel('storages.'.$storage->id)->assertOk()->assertJsonStructure(['auth']);
});

it('denies the storage channel to users without access', function () {
    $storage = Storage::factory()->create();

    $this->actingAs(User::factory()->create());

    authorizeChannel('storages.'.$storage->id)->assertForbidden();
});

it('denies the channel to guests', function () {
    $storage = Storage::factory()->create();

    authorizeChannel('storages.'.$storage->id)->assertForbidden();
});
