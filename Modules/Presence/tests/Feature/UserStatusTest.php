<?php

declare(strict_types=1);

namespace Modules\Presence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Identity\Models\User;
use Modules\Presence\Events\UserStatusUpdatedEvent;
use Tests\TestCase;

class UserStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/user/status')->assertUnauthorized();
    }

    public function test_a_user_with_no_status_yet_defaults_to_online(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/user/status')
            ->assertOk()
            ->assertJson(['status' => 'online']);
    }

    public function test_a_user_can_set_their_status(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/user/status', ['status' => 'busy'])
            ->assertOk()
            ->assertJson(['status' => 'busy']);

        $this->actingAs($user)
            ->getJson('/api/user/status')
            ->assertOk()
            ->assertJson(['status' => 'busy']);
    }

    public function test_setting_status_broadcasts_the_change(): void
    {
        Event::fake([UserStatusUpdatedEvent::class]);

        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/user/status', [
            'status' => 'away',
            'message' => 'Back in 10',
        ])->assertOk();

        Event::assertDispatched(
            UserStatusUpdatedEvent::class,
            fn (UserStatusUpdatedEvent $event) => $event->user->is($user)
                && $event->status->value === 'away'
                && $event->message === 'Back in 10',
        );
    }

    public function test_setting_status_logs_an_activity_with_the_transition(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/user/status', ['status' => 'busy']);

        $this->assertDatabaseHas('user_activities', [
            'user_id' => $user->id,
            'activity_type' => 'status_changed',
            'from_status' => 'online',
            'to_status' => 'busy',
        ]);
    }

    public function test_an_unknown_status_value_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/user/status', ['status' => 'on-fire'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }
}
