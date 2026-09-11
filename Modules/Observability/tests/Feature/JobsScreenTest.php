<?php

declare(strict_types=1);

namespace Modules\Observability\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Identity\Models\User;
use Modules\Observability\Events\JobProgressUpdated;
use Modules\Observability\Jobs\DemoProgressJob;
use Tests\TestCase;

class JobsScreenTest extends TestCase
{
    use RefreshDatabase;

    /** Both layouts ship this test; --mvc moves the frontend out of the module. */
    private function frontendFile(string $modular, string $flat): string
    {
        foreach ([$modular, $flat] as $candidate) {
            if (is_file(base_path($candidate))) {
                return base_path($candidate);
            }
        }

        $this->fail("não achei {$flat}");
    }

    public function test_the_screen_is_closed_to_non_admins(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/system/jobs')
            ->assertForbidden();
    }

    public function test_the_screen_is_open_to_admins(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/system/jobs')
            ->assertOk()
            // Second argument off: Inertia's page finder only looks under
            // resources/js/Pages, so it cannot resolve a module's own pages.
            // The file is asserted separately below instead.
            ->assertInertia(fn ($page) => $page->component('Jobs/Index', false)->has('jobs'));
    }

    public function test_the_page_the_controller_renders_exists(): void
    {
        $this->assertFileExists($this->frontendFile(
            'Modules/Observability/resources/assets/js/Pages/Jobs/Index.vue',
            'resources/js/Pages/Jobs/Index.vue',
        ));
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get('/system/jobs')->assertRedirect('/login');
    }

    public function test_admins_can_dispatch_the_demo_job(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/system/jobs/demo')
            ->assertRedirect();

        Queue::assertPushed(DemoProgressJob::class);
    }

    public function test_non_admins_cannot(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post('/system/jobs/demo')
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    /**
     * Same invariant PresenceChannelTest pins for status: the name the client
     * subscribes to has to be the one the event publishes to, and it has to be
     * authorized. Echo adds a `private-` on the way out and Laravel strips one
     * on the way in, so a name written with the prefix on either side ends up
     * doubled — authorizing fine and delivering nothing.
     */
    public function test_the_client_subscribes_to_the_channel_the_event_publishes_to(): void
    {
        $published = (new JobProgressUpdated('x', 'y', 1, now()->toIso8601String()))->broadcastOn()[0]->name;

        preg_match("/echo\.private\('([^']+)'\)/", file_get_contents($this->frontendFile(
            'Modules/Observability/resources/assets/js/composables/useJobProgress.ts',
            'resources/js/composables/useJobProgress.ts',
        )), $matches);

        $this->assertSame($published, 'private-'.($matches[1] ?? ''));
        $this->assertStringContainsString("Broadcast::channel('{$matches[1]}'", file_get_contents(base_path('routes/channels.php')));
    }
}
