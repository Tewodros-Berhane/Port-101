<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationsController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->hasPermission('core.notifications.view'), 403);

        $notifications = $request->user()
            ?->notifications()
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('core/notifications/index', [
            'notifications' => $notifications?->through(function ($notification) {
                $payload = is_array($notification->data)
                    ? $notification->data
                    : [];

                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $payload['title'] ?? 'Notification',
                    'message' => $payload['message'] ?? '',
                    'url' => $payload['url'] ?? null,
                    'meta' => $payload['meta'] ?? [],
                    'read_at' => $notification->read_at?->toIso8601String(),
                    'created_at' => $notification->created_at?->toIso8601String(),
                ];
            }),
        ]);
    }

    public function markRead(Request $request, string $notificationId): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('core.notifications.manage'), 403);

        $notification = $request->user()
            ?->notifications()
            ->where('id', $notificationId)
            ->firstOrFail();

        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        return back(303);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('core.notifications.manage'), 403);

        $request->user()?->unreadNotifications()->update([
            'read_at' => now(),
        ]);

        return back(303)->with('success', 'All notifications marked as read.');
    }

    public function destroy(Request $request, string $notificationId): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('core.notifications.manage'), 403);

        $notification = $request->user()
            ?->notifications()
            ->where('id', $notificationId)
            ->firstOrFail();

        $notification->delete();

        return back(303)->with('success', 'Notification removed.');
    }
}

