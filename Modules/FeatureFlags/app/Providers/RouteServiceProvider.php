<?php

declare(strict_types=1);

namespace Modules\FeatureFlags\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'FeatureFlags';

    public function boot(): void
    {
        parent::boot();
    }

    /**
     * routes/web.php and routes/api.php each declare their own full
     * middleware/prefix/name group already (matching how the pre-module
     * root route files worked), so they're required directly rather than
     * wrapped in this provider's own group — wrapping would double-apply
     * the 'web'/'api' middleware groups.
     */
    public function map(): void
    {
        $this->mapWebRoutes();
        $this->mapApiRoutes();
    }

    protected function mapWebRoutes(): void
    {
        $this->loadRoutesFrom(module_path($this->name, '/routes/web.php'));
    }

    protected function mapApiRoutes(): void
    {
        $this->loadRoutesFrom(module_path($this->name, '/routes/api.php'));
    }
}
