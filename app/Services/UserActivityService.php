<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserStatusEnum;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Redis;

final readonly class UserActivityService
{
    public function __construct() {}

    public function logStatusChange(User $user, UserStatusEnum $from, UserStatusEnum $to): UserActivity
    {
        return UserActivity::create([
            'user_id' => $user->id,
            'activity_type' => 'status_changed',
            'from_status' => $from->value,
            'to_status' => $to->value,
            'metadata' => null,
        ]);
    }

    public function getUserActivities(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $user->activities()
            ->recent()
            ->paginate($perPage);
    }

    public function logPageView(User $user, string $path): void
    {
        $throttleKey = "user:{$user->id}:page_view_throttle";

        // Only log once per minute
        if (Redis::exists($throttleKey)) {
            return;
        }

        UserActivity::create([
            'user_id' => $user->id,
            'activity_type' => 'page_view',
            'from_status' => null,
            'to_status' => null,
            'metadata' => [
                'path' => $path,
            ],
        ]);

        Redis::setex($throttleKey, 60, '1');
    }
}
