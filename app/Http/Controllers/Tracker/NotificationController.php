<?php

namespace App\Http\Controllers\Tracker;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Support\Alerts;
use App\Support\DisplayCurrency;
use App\Support\Labels;
use Illuminate\Http\Request;

/** The app's Notifications screen: what admins sent, then the budget alerts. */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user  = $request->user();
        $month = Labels::now()->format('Y-m');

        $messages = AdminNotification::forUser($user)
            ->latest()->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (AdminNotification $n) => [
                'title' => $n->title,
                'body'  => $n->body,
                'sent'  => Labels::moment($n->created_at),
            ]);

        return view('tracker.notifications', [
            'messages'   => $messages,
            'alerts'     => Alerts::forMonth($user, $month, DisplayCurrency::for($user))['items'],
            'monthLabel' => Labels::month($month),
        ]);
    }
}
