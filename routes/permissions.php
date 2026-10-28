<?php

use App\Http\Controllers\Permission\PermissionTemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')
    ->prefix('permission-templates')
    ->name('permission-templates.')
    ->controller(PermissionTemplateController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{template}/edit', 'edit')->name('edit');
        Route::put('{template}', 'update')->name('update');
        Route::delete('{template}', 'destroy')->name('destroy');
    });
