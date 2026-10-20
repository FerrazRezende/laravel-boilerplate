<?php

declare(strict_types=1);

namespace Modules\Presence\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Presence\Services\UserActivityService;
use Symfony\Component\HttpFoundation\Response;

final class TrackUserPresence
{
    public function __construct(
        private readonly UserActivityService $activityService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // While an admin impersonates someone, the pages are the admin's
        // browsing, not the user's. The session key is the one
        // Permissions' ImpersonateController sets.
        if (! Auth::check() || ($request->hasSession() && $request->session()->has('impersonating_id'))) {
            return $next($request);
        }

        // Log activity to MySQL (throttled to 1 per minute per user)
        $this->activityService->logPageView(Auth::user(), $request->path());

        return $next($request);
    }
}
