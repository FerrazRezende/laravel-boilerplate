<?php

declare(strict_types=1);

namespace Modules\Presence\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Identity\Models\User;
use Modules\Presence\Enums\UserStatusEnum;

final class UserStatusUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * A status change is a handful of fields going to people looking at the
     * screen right now, so it rides the fast queue rather than waiting behind
     * whatever bulk work happens to be on the default one.
     */
    public $broadcastQueue = 'high';

    public function __construct(
        public readonly User $user,
        public readonly UserStatusEnum $status,
        public readonly string $message,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('presence'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'message' => $this->message,
            'avatar_url' => $this->user->avatar,
            'updated_at' => $this->user->updated_at->toIso8601String(),
        ];
    }
}
