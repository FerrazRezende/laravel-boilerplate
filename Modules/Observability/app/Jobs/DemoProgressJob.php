<?php

declare(strict_types=1);

namespace Modules\Observability\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Observability\Traits\TracksProgress;

/**
 * Reference consumer for TracksProgress, and what gives the jobs screen
 * something to show in a project that has no queued work of its own yet.
 */
class DemoProgressJob implements ShouldQueue
{
    use Queueable, TracksProgress;

    private const STEPS = 40;

    public function __construct()
    {
        $this->initializeTracksProgress();
    }

    public function progressLabel(): string
    {
        return __('Demo job');
    }

    public function handle(): void
    {
        for ($step = 0; $step <= self::STEPS; $step++) {
            $this->progress($step, self::STEPS);

            usleep(250_000);
        }

        $this->clearProgress();
    }

    public function failed(): void
    {
        $this->clearProgress();
    }
}
