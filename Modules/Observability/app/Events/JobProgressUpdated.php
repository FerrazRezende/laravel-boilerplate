<?php

declare(strict_types=1);

namespace Modules\Observability\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class JobProgressUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $jobId,
        public readonly string $label,
        public readonly int $percentage,
        public readonly string $startedAt,
        public readonly ?string $dispatchedBy = null,
    ) {}

    /**
     * Operators always get it. The dispatcher only exists when a job was
     * started from a request, so a scheduled or console-dispatched job simply
     * has no second channel — never a broken one.
     *
     * Names carry no `private-` prefix: PrivateChannel adds it, and Laravel
     * strips one back off before matching routes/channels.php.
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('jobs-admin')];

        if ($this->dispatchedBy !== null) {
            $channels[] = new PrivateChannel("jobs.{$this->dispatchedBy}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'job.progress';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->jobId,
            'label' => $this->label,
            'percentage' => $this->percentage,
            'started_at' => $this->startedAt,
        ];
    }
}
