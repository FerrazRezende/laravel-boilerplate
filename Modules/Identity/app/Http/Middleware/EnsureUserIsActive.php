<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Login already refuses a deactivated account; this ends the sessions and
 * tokens it held before an admin switched it off, which would otherwise keep
 * working until they expired.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->isActive()) {
            return $next($request);
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->expectsJson() || ! $request->hasSession()) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        return redirect()->route('login');
    }
}
