<?php

use App\Models\Storage;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return $user->id === $id;
});

// Ход фоновых операций хранилища видят те, кому доступна страница хранилища
Broadcast::channel('storages.{storage}', function (User $user, Storage $storage) {
    return $user->can('view', $storage);
});
