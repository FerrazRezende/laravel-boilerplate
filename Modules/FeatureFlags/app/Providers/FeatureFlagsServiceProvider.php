<?php

declare(strict_types=1);

namespace Modules\FeatureFlags\Providers;

use Laravel\Pennant\Feature;
use Modules\FeatureFlags\Services\FeatureFlagService;
use Modules\Identity\Models\User;
use Nwidart\Modules\Support\ModuleServiceProvider;

class FeatureFlagsServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'FeatureFlags';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'featureflags';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        // The base ModuleServiceProvider merges config/config.php under the
        // 'featureflags' key; also merge it under 'features' so every
        // pre-existing config('features.xxx') call site keeps working.
        $this->mergeConfigFrom(module_path($this->name, 'config/config.php'), 'features');

        $definitions = config('features.definitions', []);

        foreach ($definitions as $featureName => $definition) {
            Feature::define($featureName, function (User $user) use ($featureName) {
                return $this->app->make(FeatureFlagService::class)->resolve($featureName, $user);
            });
        }
    }
}
