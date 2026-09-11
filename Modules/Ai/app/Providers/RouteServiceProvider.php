<?php

declare(strict_types=1);

namespace Modules\Ai\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Ai';

    public function boot(): void
    {
        parent::boot();
    }

    /**
     * routes/web.php declares its own full middleware/prefix/name group, so it
     * is required directly rather than wrapped — wrapping would double-apply
     * the 'web' group. There is no api.php.
     */
    public function map(): void
    {
        $this->loadRoutesFrom(module_path($this->name, '/routes/web.php'));
    }
}
