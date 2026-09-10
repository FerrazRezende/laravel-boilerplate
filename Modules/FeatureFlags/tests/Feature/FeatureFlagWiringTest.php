<?php

declare(strict_types=1);

namespace Modules\FeatureFlags\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Guards the convention rather than any single feature: a flag that is defined
 * but never applied still appears in /system/features, so an admin can toggle
 * it and watch nothing happen.
 */
class FeatureFlagWiringTest extends TestCase
{
    /**
     * @return array<int, string>
     */
    private function gatedFeatureNames(): array
    {
        $names = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'feature:')) {
                    foreach (explode(',', substr($middleware, strlen('feature:'))) as $name) {
                        $names[] = $name;
                    }
                }
            }
        }

        return array_values(array_unique($names));
    }

    public function test_every_defined_feature_gates_at_least_one_route(): void
    {
        $defined = array_keys(config('features.definitions', []));

        if ($defined === []) {
            $this->markTestSkipped('No features defined yet.');
        }

        $unused = array_diff($defined, $this->gatedFeatureNames());

        $this->assertSame([], array_values($unused), sprintf(
            'Defined in config/features.php but not applied to any route: %s. '
            .'Add feature:<name> to the feature\'s route group.',
            implode(', ', $unused),
        ));
    }

    public function test_every_gated_route_names_a_defined_feature(): void
    {
        $defined = array_keys(config('features.definitions', []));

        $unknown = array_diff($this->gatedFeatureNames(), $defined);

        $this->assertSame([], array_values($unknown), sprintf(
            'Routes gate on features that config/features.php does not define: %s. '
            .'The flag would never be togglable from /system/features.',
            implode(', ', $unknown),
        ));
    }
}
