<?php

declare(strict_types=1);

namespace Modules\Presence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Models\User;
use Modules\Presence\Enums\UserActivityTypeEnum;
use Modules\Presence\Models\UserActivity;
use Tests\TestCase;

class ActivityPruningTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_older_than_the_retention_window_is_pruned(): void
    {
        $user = User::factory()->create();

        $old = UserActivity::create(['user_id' => $user->id, 'activity_type' => UserActivityTypeEnum::PAGE_VIEW]);
        $old->forceFill(['created_at' => now()->subDays(UserActivity::RETENTION_DAYS + 1)])->save();

        $recent = UserActivity::create(['user_id' => $user->id, 'activity_type' => UserActivityTypeEnum::PAGE_VIEW]);

        $this->artisan('model:prune', ['--model' => [UserActivity::class]])->assertSuccessful();

        $this->assertNull(UserActivity::find($old->id));
        $this->assertNotNull(UserActivity::find($recent->id));
    }
}
