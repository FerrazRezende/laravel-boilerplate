<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Inertia\Middleware;
use Modules\FeatureFlags\Services\FeatureFlagService;
use Modules\Identity\Models\User;
use Modules\Presence\Services\UserStatusService;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $activeFeatures = [];
        $userPermissions = [];
        $userStatus = null;

        if ($user) {
            $featureService = app(FeatureFlagService::class);
            $activeFeatures = $featureService->getActiveFeaturesForUser($user);

            // Get all permissions for the user (excluding denied)
            $userPermissions = $user->getActualPermissions()->pluck('name')->toArray();

            // Get user's current status
            $statusService = app(UserStatusService::class);
            $statusWithMeta = $statusService->getStatusWithMetadata($user->id);
            $userStatus = $statusWithMeta ? $statusWithMeta['status'] : 'offline';
        }

        // Build impersonation data
        $impersonating = [
            'is_impersonating' => Session::has('impersonating_id'),
            'original_user_id' => Session::get('original_user_id'),
        ];

        // If impersonating, fetch both original and target user details
        if ($impersonating['is_impersonating']) {
            $originalUserId = Session::get('original_user_id');
            $originalUser = User::find($originalUserId);

            $impersonating['original_user'] = $originalUser ? [
                'name' => $originalUser->name,
                'email' => $originalUser->email,
            ] : null;

            $impersonating['target_user'] = [
                'name' => $user->name,
                'email' => $user->email,
            ];
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'activeFeatures' => $activeFeatures,
            // Visitor-facing, so it cannot come from activeFeatures: those are
            // resolved per user and a guest has none. (Ai module)
            'aiEnabled' => filled(config('ai.providers.'.config('ai.default').'.key')),
            'userPermissions' => $userPermissions,
            'userStatus' => $userStatus,
            'impersonating' => $impersonating,
            // `locale` and `translations` are shared by ShareTranslationsMiddleware,
            // which runs after SetLocaleMiddleware. Sharing them here too would
            // read the locale before it is set, and be overwritten anyway.
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'messageType' => fn () => $request->session()->get('messageType'),
            ],
        ];
    }
}
