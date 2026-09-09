<?php

use App\Http\Controllers\Api\V1\FeatureController;
use App\Http\Middleware\SetLocaleMiddleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
|
| Public API for end users to check their feature flags.
|
*/

// SetLocaleMiddleware is not optional here. Under Octane the application
// instance outlives the request, so a group that never sets a locale inherits
// whatever the worker's previous request left behind.
Route::middleware(['auth:sanctum', SetLocaleMiddleware::class])
    ->prefix('api/v1')
    ->name('api.v1.')
    ->group(function () {
        Route::get('/features', [FeatureController::class, 'index'])->name('features.index');
        Route::get('/features/{feature}', [FeatureController::class, 'show'])->name('features.show');
    });
