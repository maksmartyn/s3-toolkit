<?php

use App\Http\Controllers\User\UserController;
use App\Http\Controllers\User\UserPermissionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('users')->name('users.')->controller(UserController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');

    Route::get('{user}/edit', 'edit')->withTrashed()->name('edit');
    Route::put('{user}', 'update')->name('update');
    Route::delete('{user}', 'destroy')->name('destroy');
    Route::post('{user}/block', 'block')->name('block');
    Route::post('{user}/unblock', 'unblock')->name('unblock');
    Route::post('{user}/restore', 'restore')->withTrashed()->name('restore');
    Route::post('{user}/reset-password', 'resetPassword')->name('reset-password');
});

Route::middleware('auth')->put('users/{user}/permissions', [UserPermissionController::class, 'update'])
    ->name('users.permissions.update');
