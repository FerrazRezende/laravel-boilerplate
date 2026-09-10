<?php

declare(strict_types=1);

namespace Modules\Permissions\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Permissions\Http\Resources\PermissionResource;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    /**
     * Display a listing of permissions.
     */
    public function index(Request $request)
    {
        abort_unless($request->user()->is_admin, 403);

        $permissions = Permission::orderBy('name')->paginate(50);
        $roles = Role::with('permissions')
            ->withCount('users')
            ->orderBy('name')
            ->paginate(50);

        if ($request->wantsJson()) {
            return PermissionResource::collection($permissions);
        }

        return Inertia::render('Permissions/Index', [
            'permissions' => $permissions->items(),
            'roles' => $roles->items(),
        ]);
    }
}
