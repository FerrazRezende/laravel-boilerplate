<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserStatusEnum;
use App\Models\User;
use Illuminate\Support\Facades\Redis;

/**
 * Persists the status a user explicitly chose (online/away/busy/offline via
 * the status picker). This is not the same question as "is this user's
 * browser currently connected" — that is answered live by a Reverb presence
 * channel (see routes/channels.php and useOnlinePresence.ts), which needs no
 * server-side storage at all. A user with no connection is
 * shown as offline by whoever's watching regardless of what this service
 * says, so this class only ever needs to track the chosen status while it's
 * fresh.
 */
final readonly class UserStatusService
{
    private const int STATUS_TTL = 300; // 5 minutes

    public function setOnline(User $user): void
    {
        $this->setStatus($user, UserStatusEnum::ONLINE);
    }

    /**
     * @return array{from: UserStatusEnum, to: UserStatusEnum}
     */
    public function setStatus(User $user, UserStatusEnum $status): array
    {
        $previousStatus = $this->getStatus($user);

        Redis::setex("user:{$user->id}:status", self::STATUS_TTL, json_encode([
            'status' => $status->value,
            'updated_at' => now()->toIso8601String(),
        ]));

        return [
            'from' => $previousStatus,
            'to' => $status,
        ];
    }

    public function getStatus(User $user): UserStatusEnum
    {
        $data = Redis::get("user:{$user->id}:status");

        if ($data === false || $data === null) {
            return UserStatusEnum::ONLINE;
        }

        return UserStatusEnum::from(json_decode($data, true)['status']);
    }

    /**
     * @param  array<int>  $userIds
     * @return array<int, string>
     */
    public function getMultipleStatuses(array $userIds): array
    {
        $statuses = [];

        foreach ($userIds as $userId) {
            $data = Redis::get("user:{$userId}:status");
            $statuses[$userId] = $data !== false && $data !== null
                ? json_decode($data, true)['status']
                : UserStatusEnum::ONLINE->value;
        }

        return $statuses;
    }

    /**
     * Get all user IDs with a specific chosen status.
     *
     * @param  string  $status  The status value (online, away, busy, offline)
     * @return array<string> Array of user IDs (strings for KSUID)
     */
    public function getUserIdsWithStatus(string $status): array
    {
        $userIds = [];
        $prefix = config('database.redis.options.prefix', '');
        $keys = Redis::connection()->keys('user:*:status');

        foreach ($keys as $key) {
            $cleanKey = $prefix ? str_replace($prefix, '', $key) : $key;
            $userId = explode(':', $cleanKey)[1];

            $data = Redis::get($cleanKey);
            if ($data === false || $data === null) {
                continue;
            }

            if (json_decode($data, true)['status'] === $status) {
                $userIds[] = $userId;
            }
        }

        return $userIds;
    }

    /**
     * Get all chosen statuses, indexed by user ID.
     *
     * @return array<string, string>
     */
    public function getAllStatuses(): array
    {
        $statuses = [];
        $prefix = config('database.redis.options.prefix', '');
        $keys = Redis::connection()->keys('user:*:status');

        foreach ($keys as $key) {
            $cleanKey = $prefix ? str_replace($prefix, '', $key) : $key;
            $userId = explode(':', $cleanKey)[1];

            $data = Redis::get($cleanKey);
            if ($data === false || $data === null) {
                continue;
            }

            $statuses[$userId] = json_decode($data, true)['status'];
        }

        return $statuses;
    }

    /**
     * @return ?array{status: string, updated_at: string}
     */
    public function getStatusWithMetadata(string $userId): ?array
    {
        $data = Redis::get("user:{$userId}:status");

        if ($data === false || $data === null) {
            return null;
        }

        $decoded = json_decode($data, true);

        if (! isset($decoded['status'], $decoded['updated_at'])) {
            return null;
        }

        return [
            'status' => $decoded['status'],
            'updated_at' => $decoded['updated_at'],
        ];
    }
}
