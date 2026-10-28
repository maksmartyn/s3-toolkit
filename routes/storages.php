<?php

use App\Http\Controllers\Bucket\BucketController;
use App\Http\Controllers\Bucket\BucketSettingsController;
use App\Http\Controllers\Storage\StorageAccessController;
use App\Http\Controllers\Storage\StorageController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('storages')->name('storages.')->group(function () {
    Route::controller(StorageController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        // Каждая проверка — запрос с сервера на указанный endpoint, поэтому ограничиваем частоту
        Route::post('test-connection', 'testConnection')->middleware('throttle:20,1')->name('test-connection');
        Route::get('{storage}', 'show')->name('show');
        Route::get('{storage}/edit', 'edit')->name('edit');
        Route::put('{storage}', 'update')->name('update');
        Route::delete('{storage}', 'destroy')->name('destroy');
    });

    Route::put('{storage}/access', [StorageAccessController::class, 'update'])->name('access.update');

    Route::controller(BucketController::class)->prefix('{storage}/buckets')->name('buckets.')->group(function () {
        Route::post('/', 'store')->name('store');
        Route::delete('{bucket}', 'destroy')->name('destroy');
        Route::post('{bucket}/stats', 'stats')->name('stats');
    });

    Route::controller(BucketSettingsController::class)->prefix('{storage}/buckets/{bucket}')->name('buckets.')->group(function () {
        Route::get('/', 'show')->name('show');
        Route::put('versioning', 'versioning')->name('versioning');
        Route::put('tags', 'tags')->name('tags');
        Route::put('policy', 'policy')->name('policy');
        Route::put('cors', 'cors')->name('cors');
        Route::put('lifecycle', 'lifecycle')->name('lifecycle');
        Route::put('acl', 'acl')->name('acl');
        Route::put('website', 'website')->name('website');
        Route::put('object-lock', 'objectLock')->name('object-lock');
    });
});
