<?php

declare(strict_types=1);

namespace Modules\FeatureFlags\Providers;

use Carbon\Carbon;
use Laravel\Pennant\Feature;
use Modules\FeatureFlags\Enums\RolloutStrategyEnum;
use Modules\FeatureFlags\Models\FeatureSetting;
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
                return $this->resolveFeature($user, $featureName);
            });
        }
    }

    /**
     * Resolve feature state for a user based on rollout strategy.
     */
    protected function resolveFeature(User $user, string $featureName): bool
    {
        // God Admin always has access to all features
        if ($user->is_admin === true) {
            return true;
        }

        $setting = FeatureSetting::where('feature_name', $featureName)->first();

        // No setting yet: fall back to the environment default
        if (! $setting) {
            return $this->isFeatureActiveByDefault($featureName);
        }

        // Setting exists but is inactive: feature is off
        if (! $setting->is_active) {
            return false;
        }

        return match ($setting->strategy) {
            RolloutStrategyEnum::ALL => true,
            RolloutStrategyEnum::PERCENTAGE => $this->checkPercentage((string) $user->id, $setting->percentage),
            RolloutStrategyEnum::USERS => in_array((string) $user->id, $setting->user_ids ?? []),
            RolloutStrategyEnum::INACTIVE => false,
        };
    }

    /**
     * Check if a feature is active by default based on environment.
     */
    protected function isFeatureActiveByDefault(string $featureName): bool
    {
        $env = config('app.env');

        // Dev/local/testing: every implemented feature is active
        if (in_array($env, ['local', 'development', 'dev', 'testing'])) {
            return true;
        }

        // Production: active once implemented_at reaches FIRST_DEPLOY_DATE
        $feature = config("features.definitions.{$featureName}");
        $deployDate = config('features.first_deploy_date');

        if (! ($feature['implemented_at'] ?? null) || ! $deployDate) {
            return false;
        }

        return Carbon::parse($feature['implemented_at'])
            ->lte(Carbon::parse($deployDate));
    }

    /**
     * Deterministic percentage check based on user ID hash.
     * Same user always gets the same result for the same percentage.
     */
    protected function checkPercentage(string $userId, int $percentage): bool
    {
        $hash = crc32($userId);

        return ($hash % 100) < $percentage;
    }
}
