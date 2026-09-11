<?php

declare(strict_types=1);

namespace Modules\Presence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Models\User;
use Modules\Presence\Enums\UserStatusEnum;
use Modules\Presence\Events\UserStatusUpdatedEvent;
use Tests\TestCase;

/**
 * The status broadcast was silently dead for a while: the server published to
 * `private-presence` while the client subscribed to `private-private-presence`,
 * because both `Broadcast::channel()` and Echo's `private()` were handed a name
 * that already carried the prefix. Nothing errors in that state — the
 * subscription still authorizes, the events just never arrive.
 *
 * These assert on the channel names as text rather than over HTTP, because
 * phpunit.xml runs with BROADCAST_CONNECTION=null and the null broadcaster
 * authorizes everything without consulting routes/channels.php at all.
 */
class PresenceChannelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Resolved rather than hardcoded because the --mvc flavour moves this file
     * to resources/js/composables/, and this test ships in both layouts.
     */
    private function composable(): string
    {
        foreach ([
            'Modules/Presence/resources/assets/js/composables/useEchoChannels.ts',
            'resources/js/composables/useEchoChannels.ts',
        ] as $candidate) {
            if (is_file(base_path($candidate))) {
                return base_path($candidate);
            }
        }

        $this->fail('não achei useEchoChannels.ts');
    }

    public function test_the_client_subscribes_to_the_channel_the_event_publishes_to(): void
    {
        // Echo prepends `private-` to whatever name the composable passes.
        preg_match("/const channelName = '([^']+)'/", file_get_contents($this->composable()), $matches);

        $this->assertSame($this->publishedChannel(), 'private-'.($matches[1] ?? ''));
    }

    public function test_the_published_channel_is_authorized_in_channels_php(): void
    {
        // Laravel strips exactly one `private-` before matching the patterns.
        $authorized = preg_replace('/^private-/', '', $this->publishedChannel());

        $this->assertMatchesRegularExpression(
            "/Broadcast::channel\('".preg_quote($authorized, '/')."'/",
            file_get_contents(base_path('routes/channels.php')),
        );
    }

    private function publishedChannel(): string
    {
        $event = new UserStatusUpdatedEvent(User::factory()->create(), UserStatusEnum::ONLINE, '');

        return $event->broadcastOn()[0]->name;
    }
}
