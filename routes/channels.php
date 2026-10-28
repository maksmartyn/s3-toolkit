<?php

use App\Domain\Enum\Permission\StoragePermission;
use App\Models\Storage;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return $user->id === $id;
});

// Ход фоновых операций (бакет, папка, ошибка) — тем, кто видит бакеты или объекты хранилища
Broadcast::channel('storages.{storage}', function (User $user, Storage $storage) {
    return $user->can('use', [$storage, StoragePermission::ViewBuckets])
        || $user->can('use', [$storage, StoragePermission::ViewObjects]);
});
