<?php

declare(strict_types=1);

namespace Modules\Observability\Tests\Feature;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Modules\Observability\Events\JobProgressUpdated;
use Modules\Observability\Services\JobProgressStore;
use Modules\Observability\Traits\TracksProgress;
use Tests\TestCase;

class JobProgressTest extends TestCase
{
    private function job(): object
    {
        // A stand-in rather than DemoProgressJob, which sleeps for ten seconds
        // by design. Redis is shared with the running app (phpunit.xml sets no
        // separate REDIS_DB), so each instance gets its own KSUID and the keys
        // expire on their own — nothing here flushes.
        return new class
        {
            use TracksProgress;

            public function progressLabel(): string
            {
                return 'Stand-in';
            }
        };
    }

    public function test_it_records_a_percentage_from_a_count_and_a_total(): void
    {
        $job = $this->job();
        $job->progress(3, 4);

        $entry = app(JobProgressStore::class)->find($job->progressId);

        $this->assertSame(75, $entry['percentage']);
        $this->assertSame('Stand-in', $entry['label']);

        $job->clearProgress();
    }

    public function test_it_accepts_a_bare_percentage_too(): void
    {
        $job = $this->job();
        $job->progress(42);

        $this->assertSame(42, app(JobProgressStore::class)->find($job->progressId)['percentage']);

        $job->clearProgress();
    }

    public function test_it_broadcasts_the_progress(): void
    {
        Event::fake([JobProgressUpdated::class]);

        $job = $this->job();
        $job->progress(1, 2);

        Event::assertDispatched(
            JobProgressUpdated::class,
            fn (JobProgressUpdated $event) => $event->jobId === $job->progressId
                && $event->percentage === 50
                && $event->label === 'Stand-in',
        );

        $job->clearProgress();
    }

    /**
     * The one that matters for the screen feeling live. A plain ShouldBroadcast
     * queues the update behind the job that is reporting it, so the bar only
     * catches up once the worker is free — which is when nobody needs it.
     */
    public function test_progress_is_broadcast_immediately_rather_than_queued_behind_the_job(): void
    {
        $this->assertInstanceOf(
            ShouldBroadcastNow::class,
            new JobProgressUpdated('abc', 'Stand-in', 50, now()->toIso8601String()),
        );
    }

    public function test_it_records_the_queue_the_job_is_running_on(): void
    {
        $job = new class
        {
            use TracksProgress;

            public $queue = 'low';
        };

        $job->progress(1, 4);

        $this->assertSame('low', app(JobProgressStore::class)->find($job->progressId)['queue']);

        $event = new JobProgressUpdated('abc', 'Stand-in', 25, now()->toIso8601String(), null, 'low');
        $this->assertSame('low', $event->broadcastWith()['queue']);

        $job->clearProgress();
    }

    public function test_a_job_that_chose_no_queue_reports_its_connection_default(): void
    {
        config(['queue.default' => 'redis', 'queue.connections.redis.queue' => 'medium']);

        $job = $this->job();
        $job->progress(1, 4);

        $this->assertSame('medium', app(JobProgressStore::class)->find($job->progressId)['queue']);

        $job->clearProgress();
    }

    public function test_it_counts_tracked_jobs_by_queue(): void
    {
        $first = new class
        {
            use TracksProgress;

            public $queue = 'high';
        };

        $second = new class
        {
            use TracksProgress;

            public $queue = 'high';
        };

        $first->progress(10);
        $second->progress(20);

        $this->assertGreaterThanOrEqual(2, app(JobProgressStore::class)->countByQueue()['high'] ?? 0);

        $first->clearProgress();
        $second->clearProgress();
    }

    public function test_a_job_with_no_dispatcher_broadcasts_only_to_operators(): void
    {
        $job = $this->job();
        $job->progress(1, 2);

        $event = new JobProgressUpdated($job->progressId, 'Stand-in', 50, now()->toIso8601String());

        $this->assertCount(1, $event->broadcastOn());
        $this->assertSame('private-jobs-admin', $event->broadcastOn()[0]->name);

        $job->clearProgress();
    }

    public function test_a_dispatched_job_also_reaches_the_user_who_started_it(): void
    {
        $event = new JobProgressUpdated('abc', 'Stand-in', 50, now()->toIso8601String(), 'user-ksuid');

        $names = array_map(fn ($channel) => $channel->name, $event->broadcastOn());

        $this->assertSame(['private-jobs-admin', 'private-jobs.user-ksuid'], $names);
    }

    public function test_the_id_survives_a_retry(): void
    {
        $job = $this->job();
        $job->progress(1, 10);

        // A retry re-runs the same serialized instance, so the id it already
        // carries is reused rather than a second bar being started.
        $first = $job->progressId;
        $job->initializeTracksProgress();

        $this->assertSame($first, $job->progressId);

        $job->clearProgress();
    }

    public function test_it_throttles_writes_but_never_drops_the_terminal_value(): void
    {
        $job = $this->job();

        $job->progress(0, 100);      // 0% — terminal, always written
        $job->progress(30, 100);     // throttled away
        $job->progress(60, 100);     // throttled away
        $job->progress(100, 100);    // 100% — terminal, always written

        $this->assertSame(100, app(JobProgressStore::class)->find($job->progressId)['percentage']);

        $job->clearProgress();
    }

    public function test_reporting_never_takes_the_job_down_with_it(): void
    {
        Redis::shouldReceive('exists')->andThrow(new \RuntimeException('redis is gone'));

        $job = $this->job();

        $job->progress(1, 2);

        $this->assertTrue(true, 'progress() swallowed the failure instead of raising');
    }

    public function test_clearing_removes_it_from_the_running_list(): void
    {
        $job = $this->job();
        $job->progress(50);

        $store = app(JobProgressStore::class);
        $ids = array_column($store->running(), 'id');
        $this->assertContains($job->progressId, $ids);

        $job->clearProgress();

        $this->assertNotContains($job->progressId, array_column($store->running(), 'id'));
    }
}
