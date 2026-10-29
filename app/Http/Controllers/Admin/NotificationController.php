<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\AdminAnnouncement;
use App\Support\AdminActivity;
use App\Support\Fcm;
use App\Support\Labels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Admin › Notifications: send a message to every user, or to one, as a push,
 * an email or both, and see what was sent. Each message is also kept, so it
 * shows in the app's inbox even on a phone that was off — or before push is
 * set up at all.
 */
class NotificationController extends Controller
{
    public const CHANNELS = ['push', 'email'];

    public function index()
    {
        return view('admin.notifications.index', [
            'history'    => AdminNotification::with(['sender', 'target'])->latest()->paginate(15),
            'configured' => Fcm::configured(),
            'devices'    => DeviceToken::count(),
            'reachable'  => DeviceToken::distinct()->count('user_id'),
            'emailable'  => self::wantsEmail(User::query())->count(),
            'from'       => config('mail.from.address'),
            'now'        => Labels::now(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'      => 'required|string|max:100',
            'body'       => 'required|string|max:500',
            'audience'   => 'required|in:' . AdminNotification::AUDIENCE_ALL . ',' . AdminNotification::AUDIENCE_USER,
            'email'      => 'nullable|required_if:audience,' . AdminNotification::AUDIENCE_USER . '|email',
            'channels'   => 'required|array|min:1',
            'channels.*' => 'in:' . implode(',', self::CHANNELS),
        ], [
            'title.required'    => 'Give the notification a title.',
            'body.required'     => 'Write the message.',
            'email.required_if' => 'Enter the email of the user to send it to.',
            'channels.required' => 'Pick push, email or both.',
        ]);

        $target = null;
        if ($request->input('audience') === AdminNotification::AUDIENCE_USER) {
            $target = User::where('email', strtolower(trim($request->input('email'))))->first();
            if (! $target) {
                throw ValidationException::withMessages(['email' => 'No account uses that email.']);
            }
        }

        $channels = array_values(array_intersect(self::CHANNELS, $request->input('channels')));
        $viaPush  = in_array('push', $channels, true);
        $viaEmail = in_array('email', $channels, true);

        $tokens = $viaPush
            ? DeviceToken::query()->when($target, fn ($q) => $q->where('user_id', $target->id))->pluck('token')->all()
            : [];
        $emailIds = $viaEmail
            ? self::wantsEmail(User::query())->when($target, fn ($q) => $q->whereKey($target->id))->pluck('id')
            : collect();
        $configured = Fcm::configured();

        $notification = AdminNotification::create([
            'sent_by'          => $request->user()->id,
            'title'            => trim($request->input('title')),
            'body'             => trim($request->input('body')),
            'audience'         => $request->input('audience'),
            'target_user_id'   => $target?->id,
            'via_push'         => $viaPush,
            'via_email'        => $viaEmail,
            'status'           => $configured || ! $viaPush ? AdminNotification::STATUS_SENT : AdminNotification::STATUS_NOT_CONFIGURED,
            'devices'          => count($tokens),
            'email_recipients' => $emailIds->count(),
        ]);
        AdminActivity::record('notification.sent',
            "sent “{$notification->title}” to " . ($target ? "user #{$target->id}" : 'all users') . ' by ' . implode(' and ', $channels),
            $notification);

        $notes = [];
        $problem = false;

        if ($viaPush) {
            if (! $configured) {
                $problem = true;
                $notes[] = 'No push went out: Firebase is not set up on the server yet. It is saved in the app\'s notification list.';
            } elseif (! $tokens) {
                $notes[] = 'No phone is signed in yet, so nothing was pushed. It is saved in the app\'s notification list.';
            } else {
                $result = Fcm::send($tokens, $notification->title, $notification->body, [
                    'type' => 'admin',
                    'id'   => (string) $notification->id,
                ]);
                $notification->update(['delivered' => $result['delivered'], 'failed' => $result['failed']]);
                // Tokens from phones that removed the app: no use keeping them.
                if ($result['invalid']) DeviceToken::whereIn('token', $result['invalid'])->delete();
                $problem = $result['failed'] > 0;
                $notes[] = "Push delivered to {$result['delivered']} of " . count($tokens) . ' device(s).';
            }
        }

        if ($viaEmail) {
            if ($emailIds->isEmpty()) {
                $notes[] = $target ? "{$target->email} turned emails off, so no email went out." : 'No user has emails turned on, so no email went out.';
            } else {
                self::sendEmails($notification, $emailIds->all());
                $notes[] = 'Emailing ' . $emailIds->count() . ' ' . ($emailIds->count() === 1 ? 'user' : 'users') . '.';
            }
        }

        $who = $target ? $target->email : 'all users';

        return redirect()->route('admin.notifications.index')
            ->with($problem ? 'error' : 'success', "Sent to {$who}. " . implode(' ', $notes));
    }

    /** Users who get emails: they have an address and kept email notifications on. */
    private static function wantsEmail(Builder $users): Builder
    {
        return $users->whereNotNull('email')->where('email', '!=', '')->where('email_notifications', true);
    }

    /**
     * One email each, after the page has answered, so a slow mail server never
     * holds up the admin — and no queue worker is needed on the server.
     *
     * @param list<int> $ids
     */
    private static function sendEmails(AdminNotification $notification, array $ids): void
    {
        $announcement = new AdminAnnouncement($notification->title, $notification->body);

        defer(function () use ($notification, $ids, $announcement) {
            ignore_user_abort(true);
            @set_time_limit(0);
            $sent = $failed = 0;
            foreach (User::whereIn('id', $ids)->lazyById(100) as $user) {
                try {
                    $user->notify($announcement);
                    $sent++;
                } catch (Throwable $e) {
                    report($e);
                    $failed++;
                }
            }
            $notification->update(['emails_sent' => $sent, 'emails_failed' => $failed]);
        });
    }
}
