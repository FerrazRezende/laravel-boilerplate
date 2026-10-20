<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocaleMiddleware;
use App\Http\Middleware\ShareTranslationsMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Modules\FeatureFlags\Http\Middleware\EnsureFeatureIsEnabled;
use Modules\Identity\Http\Middleware\EnsureUserIsActive;
use Modules\Identity\Http\Middleware\EnsureUserIsAdmin;
use Modules\Presence\Http\Middleware\TrackUserPresence;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // nginx (and any load balancer in front of it) sits on a private
        // network. Without this, $request->ip() is the proxy's address for
        // every visitor, so per-IP throttles collapse into one shared bucket.
        $middleware->trustProxies(at: [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '127.0.0.1',
            '::1',
        ]);

        $middleware->web(append: [
            EnsureUserIsActive::class,
            HandleInertiaRequests::class,
            SetLocaleMiddleware::class,
            ShareTranslationsMiddleware::class,
            AddLinkHeadersForPreloadedAssets::class,
            TrackUserPresence::class,
        ]);

        $middleware->alias([
            'feature' => EnsureFeatureIsEnabled::class,
            'active' => EnsureUserIsActive::class,
            'admin' => EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
