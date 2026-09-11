<?php

declare(strict_types=1);

namespace Modules\Observability\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Modules\Observability\Events\JobProgressUpdated;
use Modules\Observability\Services\JobProgressStore;
use Tuupola\KsuidFactory;

/**
 * Reports how far along a job is.
 *
 *     class ImportSpreadsheet implements ShouldQueue
 *     {
 *         use Queueable, TracksProgress;
 *
 *         public function handle(): void
 *         {
 *             foreach ($rows as $i => $row) {
 *                 $this->import($row);
 *                 $this->progress($i + 1, count($rows));
 *             }
 *         }
 *     }
 *
 * Override `progressLabel()` to say something better than the class name.
 */
trait TracksProgress
{
    public ?string $progressId = null;

    public ?string $progressDispatcher = null;

    public ?string $progressStartedAt = null;

    private ?int $lastReportedPercentage = null;

    /**
     * Both ids are captured here, not in handle(), because both have to travel
     * with the serialized job: the id so a retry reports against the same
     * entry instead of starting a second bar, and the dispatcher because the
     * worker has no authenticated user to ask.
     */
    public function initializeTracksProgress(): void
    {
        $this->progressId ??= (string) KsuidFactory::create();
        $this->progressDispatcher ??= Auth::id();
    }

    public function progressLabel(): string
    {
        return class_basename($this);
    }

    /**
     * Accepts a count with a total, or a bare percentage.
     *
     * Safe in a tight loop: writes are throttled to one per second, so a job
     * over 100k rows does not drown the queue it is reporting on. 0 and 100
     * always get through, so the bar starts and finishes honestly.
     */
    public function progress(int $done, ?int $total = null): void
    {
        $percentage = $total === null
            ? $done
            : ($total > 0 ? (int) floor($done / $total * 100) : 0);

        $percentage = max(0, min(100, $percentage));

        if ($percentage === $this->lastReportedPercentage) {
            return;
        }

        $terminal = $percentage === 0 || $percentage === 100;

        // Reporting must never be the reason a job fails.
        try {
            $this->initializeTracksProgress();
            $this->progressStartedAt ??= now()->toIso8601String();

            if (! $terminal && ! $this->mayReport()) {
                return;
            }

            $this->lastReportedPercentage = $percentage;

            $queue = $this->progressQueue();

            app(JobProgressStore::class)->put(
                $this->progressId,
                $this->progressLabel(),
                $percentage,
                $this->progressStartedAt,
                $queue,
            );

            broadcast(new JobProgressUpdated(
                $this->progressId,
                $this->progressLabel(),
                $percentage,
                $this->progressStartedAt,
                $this->progressDispatcher,
                $queue,
            ));
        } catch (\Throwable) {
            // Redis down, broadcaster unreachable: the job carries on.
        }
    }

    /**
     * Clears the entry so a finished or failed job leaves no bar frozen at 60%.
     */
    public function clearProgress(): void
    {
        try {
            if ($this->progressId !== null) {
                app(JobProgressStore::class)->forget($this->progressId);
            }
        } catch (\Throwable) {
            // Same reasoning as progress().
        }
    }

    /**
     * Which queue this job is running on, so the screen can group by it.
     *
     * A job that never called onQueue() carries a null `queue`, and the queue
     * it actually landed on is its connection's default — resolved here rather
     * than reported as "none", which would be a lie the screen repeats.
     */
    private function progressQueue(): ?string
    {
        $queue = property_exists($this, 'queue') ? $this->queue : null;

        if (is_string($queue) && $queue !== '') {
            return $queue;
        }

        $connection = (property_exists($this, 'connection') ? $this->connection : null)
            ?? config('queue.default');

        $default = config("queue.connections.{$connection}.queue");

        return is_string($default) ? $default : null;
    }

    /**
     * The stored value is meaningless — only the key's existence matters, and
     * its TTL is the throttle window. Same idiom as UserActivityService.
     */
    private function mayReport(): bool
    {
        $key = "job-progress:{$this->progressId}:throttle";

        if (Redis::exists($key)) {
            return false;
        }

        Redis::setex($key, 1, '1');

        return true;
    }
}
