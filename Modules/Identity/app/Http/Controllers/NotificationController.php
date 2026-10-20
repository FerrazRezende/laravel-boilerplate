<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\Models\Notification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::forUser($request->user())
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $notifications->map(fn (Notification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'message' => $n->message,
                'type' => $n->type,
                'read' => $n->read,
                'created_at' => $n->created_at->toISOString(),
            ]),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => Notification::forUser($request->user())->unread()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        Notification::forUser($request->user())
            ->whereKey($id)
            ->unread()
            ->update(['read' => true, 'read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        Notification::forUser($request->user())
            ->unread()
            ->update(['read' => true, 'read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
