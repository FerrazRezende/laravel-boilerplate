<?php

declare(strict_types=1);

namespace Modules\Profile\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class AvatarController extends Controller
{
    /**
     * Redirect to a presigned RustFS URL for the user's avatar.
     *
     * Existence is checked on the 'rustfs' disk (internal endpoint, reachable
     * from this container). The URL itself is signed on 'rustfs_public'
     * (public endpoint) — see config/filesystems.php for why those cannot be
     * the same disk.
     */
    public function show(Request $request, string $userId): RedirectResponse
    {
        $user = User::findOrFail($userId);

        $rawAvatar = $user->getRawOriginal('avatar');

        if (! $rawAvatar) {
            abort(404);
        }

        if (! Storage::disk('rustfs')->exists($rawAvatar)) {
            abort(404);
        }

        $url = Storage::disk('rustfs_public')->temporaryUrl($rawAvatar, now()->addMinutes(15));

        return redirect($url);
    }
}
