<?php

declare(strict_types=1);

namespace Modules\FeatureFlags\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Laravel\Pennant\Feature;
use Modules\FeatureFlags\Enums\FeatureHistoryActionEnum;
use Modules\FeatureFlags\Enums\RolloutStrategyEnum;
use Modules\FeatureFlags\Models\FeatureHistory;
use Modules\FeatureFlags\Models\FeatureSetting;

class FeatureFlagService
{
    /**
     * Get all available features with their current status.
     *
     * @return Collection<int, array>
     */
    public function getAllFeatures(): Collection
    {
        $definitions = config('features.definitions', []);
        $settings = FeatureSetting::all()->keyBy('feature_name');

        return collect($definitions)
            ->map(function (array $definition, string $name) use ($settings) {
                $setting = $settings->get($name);

                // With a setting, its status wins; otherwise fall back to the
                // environment default
                $isActive = $setting
                    ? $setting->is_active
                    : $this->isFeatureActiveByDefault($name);

                // Active by default with no setting: treat as "all"
                $strategy = $setting?->strategy ??
                    ($isActive ? RolloutStrategyEnum::ALL : RolloutStrategyEnum::INACTIVE);

                return $this->buildFeatureArray(
                    name: $name,
                    definition: $definition,
                    strategy: $strategy,
                    isActive: $isActive,
                    percentage: $setting?->percentage ?? 0,
                    userIds: $setting?->user_ids
                );
            })
            ->values();
    }

    /**
     * Get a single feature's configuration.
     */
    public function getFeature(string $featureName): ?array
    {
        $definition = config("features.definitions.{$featureName}");

        if (! $definition) {
            return null;
        }

        $setting = FeatureSetting::where('feature_name', $featureName)->first();

        // Same logic as getAllFeatures(), kept consistent
        $isActive = $setting
            ? $setting->is_active
            : $this->isFeatureActiveByDefault($featureName);

        $strategy = $setting?->strategy ??
            ($isActive ? RolloutStrategyEnum::ALL : RolloutStrategyEnum::INACTIVE);

        return $this->buildFeatureArray(
            name: $featureName,
            definition: $definition,
            strategy: $strategy,
            isActive: $isActive,
            percentage: $setting?->percentage ?? 0,
            userIds: $setting?->user_ids
        );
    }

    /**
     * Activate a feature with the given strategy.
     */
    public function activate(string $featureName, array $options, User $actor): array
    {
        $definition = config("features.definitions.{$featureName}");
        abort_unless($definition, 404, "Feature {$featureName} not found");

        $strategy = RolloutStrategyEnum::from($options['strategy'] ?? RolloutStrategyEnum::ALL->value);
        $percentage = $options['percentage'] ?? 0;
        $userIds = $options['user_ids'] ?? null;

        $setting = FeatureSetting::firstOrCreate(
            ['feature_name' => $featureName],
            ['strategy' => RolloutStrategyEnum::INACTIVE, 'is_active' => false]
        );

        $previousState = $setting->toArray();

        $setting->update([
            'strategy' => $strategy,
            'percentage' => $percentage,
            'user_ids' => $userIds,
            'is_active' => true,
        ]);

        $this->recordHistory($setting, FeatureHistoryActionEnum::ACTIVATED, $actor, $previousState, $setting->fresh()->toArray());

        // Purge Pennant cache so feature gets re-resolved
        Feature::purge($featureName);

        return $this->getFeature($featureName);
    }

    /**
     * Deactivate a feature.
     */
    public function deactivate(string $featureName, User $actor): array
    {
        $definition = config("features.definitions.{$featureName}");
        abort_unless($definition, 404, "Feature {$featureName} not found");

        $setting = FeatureSetting::firstOrCreate(
            ['feature_name' => $featureName],
            ['strategy' => RolloutStrategyEnum::INACTIVE, 'is_active' => false]
        );

        $previousState = $setting->toArray();

        $setting->update([
            'strategy' => RolloutStrategyEnum::INACTIVE,
            'percentage' => 0,
            'is_active' => false,
        ]);

        $this->recordHistory($setting, FeatureHistoryActionEnum::DEACTIVATED, $actor, $previousState, $setting->fresh()->toArray());

        // Purge Pennant cache so feature gets re-resolved
        Feature::purge($featureName);

        return $this->getFeature($featureName);
    }

    /**
     * Update feature rollout settings.
     */
    public function update(string $featureName, array $options, User $actor): array
    {
        $definition = config("features.definitions.{$featureName}");
        abort_unless($definition, 404, "Feature {$featureName} not found");

        $setting = FeatureSetting::where('feature_name', $featureName)->firstOrFail();
        $previousState = $setting->toArray();

        $updateData = array_filter([
            'percentage' => $options['percentage'] ?? null,
            'user_ids' => $options['user_ids'] ?? null,
        ], fn ($v) => $v !== null);

        if (! empty($updateData)) {
            $setting->update($updateData);
            $this->recordHistory($setting, FeatureHistoryActionEnum::UPDATED, $actor, $previousState, $setting->fresh()->toArray());

            // Purge Pennant cache so feature gets re-resolved
            Feature::purge($featureName);
        }

        return $this->getFeature($featureName);
    }

    /**
     * Add a user to the feature's allowlist.
     */
    public function addUser(string $featureName, string $userId, User $actor): array
    {
        $setting = FeatureSetting::where('feature_name', $featureName)->firstOrFail();
        $previousState = $setting->toArray();

        $userIds = $setting->user_ids ?? [];
        if (! in_array($userId, $userIds)) {
            $userIds[] = $userId;
            $setting->update(['user_ids' => $userIds]);
            $this->recordHistory($setting, FeatureHistoryActionEnum::UPDATED, $actor, $previousState, $setting->fresh()->toArray());

            // Purge Pennant cache so feature gets re-resolved
            Feature::purge($featureName);
        }

        return $this->getFeature($featureName);
    }

    /**
     * Remove a user from the feature's allowlist.
     */
    public function removeUser(string $featureName, string $userId, User $actor): array
    {
        $setting = FeatureSetting::where('feature_name', $featureName)->firstOrFail();
        $previousState = $setting->toArray();

        $userIds = $setting->user_ids ?? [];
        $userIds = array_values(array_diff($userIds, [$userId]));

        $setting->update(['user_ids' => $userIds ?: null]);
        $this->recordHistory($setting, FeatureHistoryActionEnum::UPDATED, $actor, $previousState, $setting->fresh()->toArray());

        // Purge Pennant cache so feature gets re-resolved
        Feature::purge($featureName);

        return $this->getFeature($featureName);
    }

    /**
     * Check if a feature is active for a specific user.
     */
    public function isActiveForUser(string $featureName, User $user): bool
    {
        // God Admin always sees all features
        if ($user->is_admin === true) {
            return true;
        }

        $setting = FeatureSetting::where('feature_name', $featureName)->first();

        // No setting in the database: fall back to the environment default
        if (! $setting) {
            return $this->isFeatureActiveByDefault($featureName);
        }

        // Setting exists but is inactive: feature is off
        if (! $setting->is_active) {
            return false;
        }

        // Setting is active: follow its rollout strategy
        return match ($setting->strategy) {
            RolloutStrategyEnum::ALL => true,
            RolloutStrategyEnum::PERCENTAGE => $this->checkPercentage($user->id, $setting->percentage),
            RolloutStrategyEnum::USERS => in_array((string) $user->id, $setting->user_ids ?? []),
            RolloutStrategyEnum::INACTIVE => false,
        };
    }

    /**
     * Get features active for a specific user.
     */
    public function getActiveFeaturesForUser(User $user): array
    {
        $allFeatures = $this->getAllFeatures();

        return $allFeatures
            ->filter(fn (array $feature) => $this->isActiveForUser($feature['name'], $user))
            ->map(fn (array $feature) => $feature['name'])
            ->values()
            ->toArray();
    }

    /**
     * Get history for a feature.
     */
    public function getHistory(string $featureName): Collection
    {
        $setting = FeatureSetting::where('feature_name', $featureName)->first();

        if (! $setting) {
            return collect();
        }

        return $setting->histories()
            ->with('actor')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (FeatureHistory $history) => [
                'id' => $history->id,
                'action' => $history->action,
                'actor' => $history->actor?->name ?? $history->actor?->email ?? __('Removed user'),
                'previous_state' => $history->previous_state,
                'new_state' => $history->new_state,
                'created_at' => $history->created_at->toISOString(),
            ]);
    }

    /**
     * Check if a feature is active by default based on environment.
     */
    public function isFeatureActiveByDefault(string $featureName): bool
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
     * Build a feature array from definition and settings.
     */
    private function buildFeatureArray(
        string $name,
        array $definition,
        RolloutStrategyEnum $strategy,
        bool $isActive,
        int $percentage = 0,
        ?array $userIds = null
    ): array {
        return [
            'name' => $name,
            'display_name' => $definition['name'],
            'description' => $definition['description'],
            'is_active' => $isActive,
            'strategy' => $strategy->value,
            'strategy_label' => $strategy->label(),
            'percentage' => $percentage,
            'user_ids' => $userIds,
        ];
    }

    /**
     * Deterministic percentage check based on user ID hash.
     */
    private function checkPercentage(string|int $userId, int $percentage): bool
    {
        $hash = crc32((string) $userId);

        return ($hash % 100) < $percentage;
    }

    /**
     * Record a change in feature history.
     */
    private function recordHistory(FeatureSetting $setting, FeatureHistoryActionEnum $action, User $actor, ?array $previous, ?array $new): void
    {
        FeatureHistory::create([
            'feature_setting_id' => $setting->id,
            'action' => $action,
            'actor_id' => $actor->id,
            'previous_state' => $previous,
            'new_state' => $new,
        ]);
    }
}
