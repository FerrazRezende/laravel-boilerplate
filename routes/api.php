<?php

declare(strict_types=1);

use App\Http\Controllers\UserStatusController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routes for API endpoints (JSON only). Notification endpoints live in the
| Identity module's own routes/web.php.
|
*/

Route::middleware(['web', 'auth'])->prefix('api')->group(function (): void {
    // User status endpoints
    Route::get('/user/status', [UserStatusController::class, 'index'])->name('api.user.status');
    Route::post('/user/status', [UserStatusController::class, 'store'])->name('api.user.status.set');
});
