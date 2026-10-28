<?php

use App\Http\Controllers\Bucket\BucketController;
use App\Http\Controllers\Bucket\BucketSettingsController;
use App\Http\Controllers\Bucket\ObjectController;
use App\Http\Controllers\Bucket\ObjectManageController;
use App\Http\Controllers\Bucket\ObjectUploadController;
use App\Http\Controllers\Storage\AccessKeyController;
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

    Route::controller(AccessKeyController::class)->prefix('{storage}/access-keys')->name('access-keys.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('status', 'status')->name('status');
        Route::delete('/', 'destroy')->name('destroy');
        Route::put('credentials', 'credentials')->name('credentials');
    });

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

    // Ключ объекта содержит «/», поэтому передаётся параметром запроса, а не частью пути
    Route::prefix('{storage}/buckets/{bucket}/objects')->name('buckets.objects.')->group(function () {
        Route::controller(ObjectController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('list', 'list')->name('list');
            Route::get('details', 'details')->name('details');
            Route::get('versions', 'versions')->name('versions');
            Route::get('acl', 'acl')->name('acl');
            Route::get('protection', 'protection')->name('protection');
            Route::get('uploads', 'uploads')->name('uploads');
            Route::get('download', 'download')->name('download');
            Route::post('share', 'share')->name('share');
        });

        Route::controller(ObjectManageController::class)->group(function () {
            Route::post('folder', 'createFolder')->name('folder');
            Route::delete('/', 'destroy')->name('destroy');
            Route::post('transfer', 'transfer')->name('transfer');
            Route::post('rename', 'rename')->name('rename');
            Route::post('folder-operation', 'folderOperation')->name('folder-operation');
            Route::put('metadata', 'metadata')->name('metadata');
            Route::put('tags', 'tags')->name('tags');
            Route::put('acl', 'acl')->name('acl.update');
            Route::put('retention', 'retention')->name('retention');
            Route::put('legal-hold', 'legalHold')->name('legal-hold');
            Route::post('restore', 'restore')->name('restore');
            Route::delete('uploads', 'abortUploads')->name('uploads.abort');
        });

        Route::controller(ObjectUploadController::class)->prefix('upload')->name('upload.')->group(function () {
            Route::post('/', 'store')->name('store');
            Route::post('multipart', 'start')->name('start');
            Route::post('multipart/part', 'part')->name('part');
            Route::post('multipart/complete', 'complete')->name('complete');
            Route::delete('multipart', 'abort')->name('abort');
        });
    });
});
