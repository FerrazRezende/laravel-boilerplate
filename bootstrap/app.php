<?php

use App\Http\Middleware\CheckDeniedPermissions;
use App\Http\Middleware\EnsureFeatureIsEnabled;
use App\Http\Middleware\HandleImpersonation;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocaleMiddleware;
use App\Http\Middleware\ShareTranslationsMiddleware;
use App\Http\Middleware\TrackUserPresence;
use App\Providers\FeatureServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        FeatureServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: fn () => collect([
            __DIR__.'/../routes/system.php',
            __DIR__.'/../routes/api_v1.php',
            __DIR__.'/../routes/api.php',
        ])->each(fn ($path) => require $path),
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
