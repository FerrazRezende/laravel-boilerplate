<?php

declare(strict_types=1);

namespace Modules\Identity\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Identity';

    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Unlike FeatureFlags' routes, auth.php's groups (guest/auth) don't
     * declare 'web' themselves — they relied on inheriting it from being
     * require()'d inside the root routes/web.php, which Laravel's own
     * routing bootstrap wraps in the 'web' group. Replicate that here.
     */
    public function map(): void
    {
        Route::middleware('web')->group(module_path($this->name, '/routes/web.php'));
    }
}
