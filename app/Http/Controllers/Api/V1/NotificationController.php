<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use Illuminate\Http\Request;

/** The app's inbox: what admins sent this user, newest first. */
class NotificationController extends Controller
{
    private const LIMIT = 50;

    public function index(Request $request)
    {
        $notifications = AdminNotification::forUser($request->user())
            ->latest()->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (AdminNotification $n) => [
                'id'      => $n->id,
                'title'   => $n->title,
                'body'    => $n->body,
                'sent_at' => $n->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $notifications]);
    }
}
