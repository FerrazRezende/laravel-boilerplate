<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Observability\Http\Controllers\JobController;

Route::middleware(['web', 'auth', 'feature:observability'])
    ->prefix('system')
    ->name('system.')
    ->group(function (): void {
        Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
        Route::post('/jobs/demo', [JobController::class, 'demo'])->name('jobs.demo');
    });
