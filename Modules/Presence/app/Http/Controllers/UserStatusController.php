<?php

declare(strict_types=1);

namespace Modules\Presence\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Presence\Enums\UserStatusEnum;
use Modules\Presence\Events\UserStatusUpdatedEvent;
use Modules\Presence\Services\UserActivityService;
use Modules\Presence\Services\UserStatusService;

final class UserStatusController extends Controller
{
    public function __construct(
        private UserStatusService $statusService,
        private UserActivityService $activityService,
    ) {}

    /**
     * Get the current user's status.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $statusWithMeta = $this->statusService->getStatusWithMetadata($user->id);

        if ($statusWithMeta === null) {
            // No status explicitly chosen yet
            return response()->json([
                'status' => UserStatusEnum::ONLINE->value,
                'updated_at' => now()->toIso8601String(),
            ]);
        }

        return response()->json($statusWithMeta);
    }

    /**
     * Set the current user's status.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'status' => ['required', Rule::enum(UserStatusEnum::class)],
            'message' => ['nullable', 'string', 'max:255'],
        ]);

        $statusEnum = UserStatusEnum::from($request->input('status'));

        $previousStatus = $this->statusService->getStatus($user);

        // Set the new status
        $this->statusService->setStatus($user, $statusEnum);

        $this->activityService->logStatusChange(
            $user,
            $previousStatus,
            $statusEnum,
            $request->input('message') ? ['message' => $request->input('message')] : null,
        );

        // Broadcast the status change
        broadcast(new UserStatusUpdatedEvent(
            $user,
            $statusEnum,
            $request->input('message', ''),
        ));

        return response()->json([
            'status' => $statusEnum->value,
            'updated_at' => now()->toIso8601String(),
        ]);
    }
}
