<?php

declare(strict_types=1);

namespace Modules\Observability\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Observability';

    public function boot(): void
    {
        parent::boot();
    }

    /**
     * routes/web.php declares its own full middleware/prefix/name group, so it
     * is required directly rather than wrapped here — wrapping would
     * double-apply the 'web' group. There is no api.php: this module has no
     * stateless endpoints.
     */
    public function map(): void
    {
        $this->loadRoutesFrom(module_path($this->name, '/routes/web.php'));
    }
}
