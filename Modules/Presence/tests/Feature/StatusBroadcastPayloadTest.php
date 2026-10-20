<?php

declare(strict_types=1);

namespace Modules\Presence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Models\User;
use Modules\Presence\Enums\UserStatusEnum;
use Modules\Presence\Events\UserStatusUpdatedEvent;
use Tests\TestCase;

class StatusBroadcastPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_status_broadcast_does_not_carry_the_email(): void
    {
        // Every signed-in user listens on this channel.
        $user = User::factory()->create();

        $payload = (new UserStatusUpdatedEvent($user, UserStatusEnum::BUSY, ''))->broadcastWith();

        $this->assertArrayNotHasKey('email', $payload);
        $this->assertSame($user->id, $payload['user_id']);
    }
}
