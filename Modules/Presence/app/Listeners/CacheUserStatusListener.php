<?php

declare(strict_types=1);

namespace Modules\Presence\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Presence\Events\UserStatusUpdatedEvent;
use Modules\Presence\Services\UserStatusService;

final class CacheUserStatusListener implements ShouldQueue
{
    public function __construct(
        private readonly UserStatusService $statusService,
    ) {}

    public function handle(UserStatusUpdatedEvent $event): void
    {
        $this->statusService->setStatus(
            user: $event->user,
            status: $event->status,
        );
    }
}
