<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocaleMiddleware;
use App\Http\Middleware\ShareTranslationsMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Modules\FeatureFlags\Http\Middleware\EnsureFeatureIsEnabled;
use Modules\Permissions\Http\Middleware\CheckDeniedPermissions;
use Modules\Permissions\Http\Middleware\HandleImpersonation;
use Modules\Presence\Http\Middleware\TrackUserPresence;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            SetLocaleMiddleware::class,
            ShareTranslationsMiddleware::class,
            AddLinkHeadersForPreloadedAssets::class,
            HandleImpersonation::class,
            CheckDeniedPermissions::class,
            TrackUserPresence::class,
        ]);

        $middleware->alias([
            'feature' => EnsureFeatureIsEnabled::class,
            'impersonate' => HandleImpersonation::class,
            'denied.check' => CheckDeniedPermissions::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
