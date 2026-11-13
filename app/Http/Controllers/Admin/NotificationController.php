<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private AdminNotificationService $notifications)
    {
    }

    /** The bell's list and unread count, fetched by the top bar every minute and when the bell opens. */
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user();

        return response()->json([
            'unread' => $this->notifications->unreadCount($admin),
            'html' => view('partials.notification-list', [
                'items' => $this->notifications->latest($admin),
                'notifier' => $this->notifications,
                'admin' => $admin,
            ])->render(),
        ]);
    }

    /** Opening the bell marks everything as seen. Also works as a normal form post without JavaScript. */
    public function read(Request $request)
    {
        $this->notifications->markRead($request->user());

        return $request->expectsJson() ? response()->json(['unread' => 0]) : back();
    }
}
