<?php

declare(strict_types=1);

namespace Modules\Permissions\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Modules\Identity\Models\User;

class ImpersonateController extends Controller
{
    /**
     * Start impersonating a user.
     */
    public function start(Request $request, User $user)
    {
        if ($user->is_admin) {
            return redirect()->back()->withErrors([
                'error' => __('Cannot impersonate another admin user.'),
            ]);
        }

        // EnsureUserIsActive would sign the admin straight out on the next
        // request, losing their own session along with it.
        if (! $user->isActive()) {
            return redirect()->back()->withErrors([
                'error' => __('Cannot impersonate a deactivated user.'),
            ]);
        }

        $admin = $request->user();

        Session::put('original_user_id', $admin->id);
        Session::put('impersonating_id', $user->id);

        Auth::login($user);
        $request->session()->regenerate();

        Log::notice('Impersonation started', [
            'admin_id' => $admin->id,
            'admin_email' => $admin->email,
            'target_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return redirect()->route('dashboard')
            ->with('info', __('You are now impersonating :name.', ['name' => $user->name]));
    }

    /**
     * Stop impersonating and return to original user.
     */
    public function stop(Request $request)
    {
        $originalUserId = Session::get('original_user_id');

        if (! $originalUserId) {
            return redirect()->route('dashboard');
        }

        $targetId = Session::get('impersonating_id');
        Session::forget(['original_user_id', 'impersonating_id']);

        $originalUser = User::findOrFail($originalUserId);
        Auth::login($originalUser);
        $request->session()->regenerate();

        Log::notice('Impersonation stopped', [
            'admin_id' => $originalUser->id,
            'target_id' => $targetId,
            'ip' => $request->ip(),
        ]);

        return redirect()->route('system.users.index')
            ->with('success', __('You are no longer impersonating.'));
    }
}
