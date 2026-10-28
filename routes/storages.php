<?php

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
});
