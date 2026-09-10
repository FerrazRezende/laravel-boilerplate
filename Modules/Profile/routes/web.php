<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Profile\Http\Controllers\AvatarController;
use Modules\Profile\Http\Controllers\LocaleController as ProfileLocaleController;
use Modules\Profile\Http\Controllers\ProfileInformationController;
use Modules\Profile\Http\Controllers\ProfilePhotoController;

Route::middleware(['web', 'auth'])->group(function () {
    // Avatar proxy - serves images from RustFS (auth required)
    Route::get('/avatars/{userId}', [AvatarController::class, 'show'])->name('avatar.show');
    Route::get('/profile', [ProfileInformationController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileInformationController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileInformationController::class, 'destroy'])->name('profile.destroy');
    Route::patch('/profile/locale', [ProfileLocaleController::class, 'update'])
        ->name('profile.locale.update')
        ->middleware(['auth', 'verified']);
    Route::post('/profile/photo', [ProfilePhotoController::class, 'store'])
        ->name('profile.photo.store');
    Route::delete('/profile/photo', [ProfilePhotoController::class, 'destroy'])
        ->name('profile.photo.destroy');
});
