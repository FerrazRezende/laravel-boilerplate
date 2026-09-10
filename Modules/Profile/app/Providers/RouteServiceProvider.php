<?php

declare(strict_types=1);

namespace Modules\Profile\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Profile';

    public function boot(): void
    {
        parent::boot();
    }

    /**
     * routes/web.php declares its own full middleware group already, so
     * it's required directly rather than wrapped in this provider's own
     * group.
     */
    public function map(): void
    {
        $this->loadRoutesFrom(module_path($this->name, '/routes/web.php'));
    }
}
