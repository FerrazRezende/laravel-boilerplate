<?php

declare(strict_types=1);

namespace Modules\Presence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Models\User;
use Modules\Presence\Models\UserActivity;
use Tests\TestCase;

class UserActivityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * No dedicated Redis database for tests (REDIS_DB is the same one the
     * running dev app uses), so nothing here flushes it — the throttle key
     * this test relies on is a real key with a real 60s TTL, same as it
     * would be for an actual visitor, and it expires on its own. A random
     * KSUID user per test means the key never collides with a real one.
     */
    public function test_visiting_a_page_logs_a_page_view_activity(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard');

        $this->assertDatabaseHas('user_activities', [
            'user_id' => $user->id,
            'activity_type' => 'page_view',
        ]);
    }

    public function test_page_views_are_throttled_to_one_per_minute(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard');
        $this->actingAs($user)->get('/dashboard');
        $this->actingAs($user)->get('/dashboard');

        $this->assertSame(
            1,
            UserActivity::forUser($user->id)->where('activity_type', 'page_view')->count(),
        );
    }

    public function test_each_user_is_throttled_independently(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice)->get('/dashboard');
        $this->actingAs($bob)->get('/dashboard');

        $this->assertSame(1, UserActivity::forUser($alice->id)->count());
        $this->assertSame(1, UserActivity::forUser($bob->id)->count());
    }
}
