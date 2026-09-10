<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Presence\Http\Controllers\UserStatusController;

Route::middleware(['web', 'auth'])->prefix('api')->group(function (): void {
    Route::get('/user/status', [UserStatusController::class, 'index'])->name('api.user.status');
    Route::post('/user/status', [UserStatusController::class, 'store'])->name('api.user.status.set');
});
