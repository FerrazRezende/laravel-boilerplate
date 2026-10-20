<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Models\Notification;
use Modules\Identity\Models\User;
use Tests\TestCase;

class UserNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function notify(User $user): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => 'schema_changed',
            'title' => 'Title',
            'message' => 'Body',
        ]);
    }

    public function test_notifications_use_ksuid_keys_in_their_own_table(): void
    {
        $notification = $this->notify(User::factory()->create());

        $this->assertSame('user_notifications', $notification->getTable());
        $this->assertSame(27, strlen($notification->id));
    }

    public function test_a_user_can_mark_their_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = $this->notify($user);

        $this->actingAs($user)->postJson("/api/notifications/{$notification->id}/read")->assertOk();

        $this->assertTrue($notification->fresh()->read);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_a_user_cannot_mark_someone_elses_notification(): void
    {
        $notification = $this->notify(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->postJson("/api/notifications/{$notification->id}/read")
            ->assertOk();

        $this->assertFalse($notification->fresh()->read);
    }
}
