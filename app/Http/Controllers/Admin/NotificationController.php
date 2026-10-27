<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\DeviceToken;
use App\Models\User;
use App\Support\Fcm;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Admin › Notifications: send a push to every user, or to one, and see what
 * was sent. Each message is also kept, so it shows in the app's inbox even on
 * a phone that was off — or before push is set up at all.
 */
class NotificationController extends Controller
{
    public function index()
    {
        return view('admin.notifications.index', [
            'history'    => AdminNotification::with(['sender', 'target'])->latest()->paginate(15),
            'configured' => Fcm::configured(),
            'devices'    => DeviceToken::count(),
            'reachable'  => DeviceToken::distinct()->count('user_id'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'    => 'required|string|max:100',
            'body'     => 'required|string|max:500',
            'audience' => 'required|in:' . AdminNotification::AUDIENCE_ALL . ',' . AdminNotification::AUDIENCE_USER,
            'email'    => 'nullable|required_if:audience,' . AdminNotification::AUDIENCE_USER . '|email',
        ], [
            'title.required'    => 'Give the notification a title.',
            'body.required'     => 'Write the message.',
            'email.required_if' => 'Enter the email of the user to send it to.',
        ]);

        $target = null;
        if ($request->input('audience') === AdminNotification::AUDIENCE_USER) {
            $target = User::where('email', strtolower(trim($request->input('email'))))->first();
            if (! $target) {
                throw ValidationException::withMessages(['email' => 'No account uses that email.']);
            }
        }

        $tokens = DeviceToken::query()
            ->when($target, fn ($q) => $q->where('user_id', $target->id))
            ->pluck('token')
            ->all();
        $configured = Fcm::configured();

        $notification = AdminNotification::create([
            'sent_by'        => $request->user()->id,
            'title'          => trim($request->input('title')),
            'body'           => trim($request->input('body')),
            'audience'       => $request->input('audience'),
            'target_user_id' => $target?->id,
            'status'         => $configured ? AdminNotification::STATUS_SENT : AdminNotification::STATUS_NOT_CONFIGURED,
            'devices'        => count($tokens),
        ]);

        if (! $configured) {
            return redirect()->route('admin.notifications.index')->with('error',
                'Saved — it will show in the app\'s notification list, but no push was sent: Firebase is not set up on the server yet.');
        }

        $result = Fcm::send($tokens, $notification->title, $notification->body, [
            'type' => 'admin',
            'id'   => (string) $notification->id,
        ]);
        $notification->update(['delivered' => $result['delivered'], 'failed' => $result['failed']]);
        // Tokens from phones that removed the app: no use keeping them.
        if ($result['invalid']) DeviceToken::whereIn('token', $result['invalid'])->delete();

        $who = $target ? $target->email : 'all users';
        $message = count($tokens) === 0
            ? "Saved for {$who}. No phone is signed in yet, so nothing was pushed — it will show in the app's notification list."
            : "Sent to {$who}: delivered to {$result['delivered']} of " . count($tokens) . ' device(s).';

        return redirect()->route('admin.notifications.index')->with($result['failed'] > 0 ? 'error' : 'success', $message);
    }
}
