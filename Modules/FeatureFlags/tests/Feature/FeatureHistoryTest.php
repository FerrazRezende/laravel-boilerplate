<?php

declare(strict_types=1);

namespace Modules\FeatureFlags\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FeatureFlags\Models\FeatureHistory;
use Modules\FeatureFlags\Providers\FeatureFlagsServiceProvider;
use Modules\FeatureFlags\Services\FeatureFlagService;
use Modules\Identity\Models\User;
use Tests\TestCase;

class FeatureHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_an_admin_keeps_the_changes_they_made(): void
    {
        // Declared here so the test does not depend on which optional modules
        // a generated project kept.
        $feature = 'history-probe';
        config()->set("features.definitions.{$feature}", [
            'name' => 'History probe',
            'description' => 'Exists only for this test.',
            'implemented_at' => null,
        ]);
        (new FeatureFlagsServiceProvider($this->app))->boot();

        $admin = User::factory()->create(['is_admin' => true]);
        $service = app(FeatureFlagService::class);
        $service->activate($feature, ['strategy' => 'all'], $admin);

        $admin->delete();

        $this->assertSame(1, FeatureHistory::count());
        $this->assertNull(FeatureHistory::first()->actor_id);
        $this->assertSame(__('Removed user'), $service->getHistory($feature)->first()['actor']);
    }
}
