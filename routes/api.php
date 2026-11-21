<?php

use App\Http\Controllers\CloudSyncApiController;
use Illuminate\Support\Facades\Route;

// Stateless Cloud Hub Sync Endpoints (25% Cloud Layer — Outlet -> Live Server)
Route::prefix('cloud')->group(function (): void {
    Route::get('/health', [CloudSyncApiController::class, 'health'])->name('api.cloud.health');
    Route::post('/sync', [CloudSyncApiController::class, 'receiveSync'])->name('api.cloud.sync');
});
