<?php

use App\Http\Controllers\Audit\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
