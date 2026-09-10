<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserActivityTypeEnum;
use App\Enums\UserStatusEnum;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Redis;

final readonly class UserActivityService
{
    public function __construct() {}

    public function logStatusChange(User $user, UserStatusEnum $from, UserStatusEnum $to, ?array $metadata = null): UserActivity
    {
        return UserActivity::create([
            'user_id' => $user->id,
            'activity_type' => UserActivityTypeEnum::STATUS_CHANGED,
            'from_status' => $from,
            'to_status' => $to,
            'metadata' => $metadata,
        ]);
    }

    public function getUserActivities(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return UserActivity::forUser($user->id)
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
            'activity_type' => UserActivityTypeEnum::PAGE_VIEW,
            'from_status' => null,
            'to_status' => null,
            'metadata' => [
                'path' => $path,
            ],
        ]);

        Redis::setex($throttleKey, 60, '1');
    }
}
