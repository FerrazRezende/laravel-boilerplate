<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Ai\Http\Controllers\ChatController;

Route::middleware('web')->prefix('ai')->name('ai.')->group(function (): void {
    Route::post('/chat', [ChatController::class, 'chat'])
        ->middleware(['auth', 'feature:ai', 'throttle:30,1'])
        ->name('chat');

    // No feature: middleware here. Flags in this app resolve per user, and a
    // visitor has none — the gate would refuse everyone. What decides whether
    // the public chat answers is whether a provider key is configured, which
    // the controller checks; without one it reports that rather than failing.
    Route::post('/public-chat', [ChatController::class, 'publicChat'])
        ->middleware('throttle:5,1')
        ->name('public-chat');
});
