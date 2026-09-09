<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FeatureSetting;
use App\Models\User;
use App\Providers\FeatureServiceProvider;
use App\Services\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class FeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    private const FEATURE = 'demo-feature';

    protected function setUp(): void
    {
        parent::setUp();

        // The provider reads definitions at boot, which already happened, so
        // the test feature has to be declared and the provider re-booted.
        config()->set('features.definitions', [
            self::FEATURE => [
                'name' => 'Demo Feature',
                'description' => 'Exists only for this test.',
                'implemented_at' => null,
            ],
        ]);

        (new FeatureServiceProvider($this->app))->boot();

        Route::middleware(['web', 'auth', 'feature:'.self::FEATURE])
            ->get('/__feature-probe', fn () => response('reached'));
    }

    private function service(): FeatureFlagService
    {
        return app(FeatureFlagService::class);
    }

    public function test_route_is_reachable_while_the_feature_is_active(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->service()->activate(self::FEATURE, ['strategy' => 'all'], $user);

        $this->actingAs($user)->get('/__feature-probe')->assertOk();
    }

    public function test_route_is_refused_once_the_feature_is_deactivated(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->service()->deactivate(self::FEATURE, $user);

        $this->actingAs($user)->get('/__feature-probe')->assertForbidden();
    }

    public function test_admins_keep_access_to_a_deactivated_feature(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->service()->deactivate(self::FEATURE, $admin);

        // Deliberate: FeatureServiceProvider::resolveFeature exempts admins from
        // flags entirely. Asserted so the exemption cannot be dropped silently.
        $this->actingAs($admin)->get('/__feature-probe')->assertOk();
    }

    public function test_editing_the_setting_row_directly_does_not_take_effect(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->service()->activate(self::FEATURE, ['strategy' => 'all'], $user);
        $this->actingAs($user)->get('/__feature-probe')->assertOk();

        FeatureSetting::where('feature_name', self::FEATURE)
            ->update(['is_active' => false, 'strategy' => 'inactive']);

        // Pennant caches the resolved value per scope, so the write above is
        // invisible until something purges it. This is why every change must go
        // through FeatureFlagService rather than touching the model.
        $this->actingAs($user)->get('/__feature-probe')->assertOk();

        Feature::purge(self::FEATURE);

        $this->actingAs($user)->get('/__feature-probe')->assertForbidden();
    }

    public function test_users_strategy_limits_the_feature_to_the_listed_users(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $allowed = User::factory()->create(['is_admin' => false]);
        $excluded = User::factory()->create(['is_admin' => false]);

        $this->service()->activate(
            self::FEATURE,
            ['strategy' => 'users', 'user_ids' => [(string) $allowed->id]],
            $admin,
        );

        $this->actingAs($allowed)->get('/__feature-probe')->assertOk();
        $this->actingAs($excluded)->get('/__feature-probe')->assertForbidden();
    }

    public function test_percentage_rollout_is_stable_for_the_same_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false]);

        $this->service()->activate(
            self::FEATURE,
            ['strategy' => 'percentage', 'percentage' => 50],
            $admin,
        );

        $first = $this->service()->isActiveForUser(self::FEATURE, $user);

        // A user must not flip between requests, otherwise a rollout would show
        // and hide the feature at random for the same person.
        for ($i = 0; $i < 5; $i++) {
            Feature::purge(self::FEATURE);
            $this->assertSame($first, $this->service()->isActiveForUser(self::FEATURE, $user));
        }
    }

    public function test_zero_and_hundred_percent_are_absolute(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false]);

        $this->service()->activate(self::FEATURE, ['strategy' => 'percentage', 'percentage' => 0], $admin);
        $this->assertFalse($this->service()->isActiveForUser(self::FEATURE, $user));

        $this->service()->activate(self::FEATURE, ['strategy' => 'percentage', 'percentage' => 100], $admin);
        $this->assertTrue($this->service()->isActiveForUser(self::FEATURE, $user));
    }

    public function test_active_features_reach_the_frontend_as_a_shared_prop(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->service()->activate(self::FEATURE, ['strategy' => 'all'], $user);

        // The whole UI gating story depends on this prop being present.
        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('activeFeatures', [self::FEATURE]));
    }
}
