<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\DeviceToken;
use App\Models\ScheduledNotification;
use App\Models\User;
use App\Support\AdminActivity;
use App\Support\AdminNotifier;
use App\Support\Fcm;
use App\Support\Labels;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Admin › Notifications: send a message to every user, or to one, as a push,
 * an email or both — now, or later on a schedule (once, every day or every
 * week) — and see what was sent and what is coming. Each message is also
 * kept, so it shows in the app's inbox even on a phone that was off, or
 * before push is set up at all.
 */
class NotificationController extends Controller
{
    public const CHANNELS = ['push', 'email'];

    public function index(Request $request)
    {
        return view('admin.notifications.index', [
            'history'    => AdminNotification::with(['sender', 'target'])->latest()->paginate(15),
            'schedules'  => ScheduledNotification::with('target')->orderByDesc('active')->orderBy('next_run_at')->latest('id')->get(),
            'tab'        => $request->query('tab') === 'scheduled' ? 'scheduled' : 'sent',
            'configured' => Fcm::configured(),
            'devices'    => DeviceToken::count(),
            'reachable'  => DeviceToken::distinct()->count('user_id'),
            'emailable'  => AdminNotifier::wantsEmail(User::query())->count(),
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
            // "Schedule": once on a date, every day, or every week on a day — at a time, India time.
            'when'       => 'nullable|in:now,later',
            'repeat'     => 'exclude_unless:when,later|required|in:' . implode(',', array_keys(ScheduledNotification::REPEATS)),
            'time'       => 'exclude_unless:when,later|required|date_format:H:i',
            'date'       => 'exclude_unless:when,later|exclude_unless:repeat,once|required|date_format:Y-m-d',
            'weekday'    => 'exclude_unless:when,later|exclude_unless:repeat,weekly|required|integer|between:0,6',
        ], [
            'title.required'    => 'Give the notification a title.',
            'body.required'     => 'Write the message.',
            'email.required_if' => 'Enter the email of the user to send it to.',
            'channels.required' => 'Pick push, email or both.',
            'time.required'     => 'Pick a time.',
            'time.date_format'  => 'Pick a time.',
            'date.required'     => 'Pick a date.',
            'date.date_format'  => 'Pick a date.',
            'weekday.required'  => 'Pick a day.',
        ]);

        $target = null;
        if ($request->input('audience') === AdminNotification::AUDIENCE_USER) {
            $target = User::where('email', strtolower(trim($request->input('email'))))->first();
            if (! $target) {
                throw ValidationException::withMessages(['email' => 'No account uses that email.']);
            }
        }

        $channels = array_values(array_intersect(self::CHANNELS, $request->input('channels')));
        $title = trim($request->input('title'));
        $body = trim($request->input('body'));
        $who = $target ? $target->email : 'all users';

        if ($request->input('when') === 'later') {
            return $this->schedule($request, $title, $body, $target, $channels);
        }

        $result = AdminNotifier::send($request->user(), $title, $body, $target,
            in_array('push', $channels, true), in_array('email', $channels, true));
        AdminActivity::record('notification.sent',
            "sent “{$title}” to " . ($target ? "user #{$target->id}" : 'all users') . ' by ' . implode(' and ', $channels),
            $result['notification']);

        return redirect()->route('admin.notifications.index')
            ->with($result['problem'] ? 'error' : 'success', "Sent to {$who}. " . implode(' ', $result['notes']));
    }

    /** @param list<string> $channels */
    private function schedule(Request $request, string $title, string $body, ?User $target, array $channels)
    {
        $schedule = new ScheduledNotification([
            'created_by'     => $request->user()->id,
            'title'          => $title,
            'body'           => $body,
            'audience'       => $target ? AdminNotification::AUDIENCE_USER : AdminNotification::AUDIENCE_ALL,
            'target_user_id' => $target?->id,
            'via_push'       => in_array('push', $channels, true),
            'via_email'      => in_array('email', $channels, true),
            'repeat'         => $request->input('repeat'),
            'time'           => $request->input('time'),
            'weekday'        => $request->input('repeat') === 'weekly' ? (int) $request->input('weekday') : null,
        ]);

        if ($schedule->repeat === 'once') {
            $at = Carbon::createFromFormat('Y-m-d H:i', $request->input('date') . ' ' . $schedule->time, config('app.display_timezone'));
            if ($at->lte(now())) {
                throw ValidationException::withMessages(['date' => 'Pick a time that has not passed yet.']);
            }
            $schedule->next_run_at = $at->utc();
        } else {
            $schedule->next_run_at = $schedule->nextRunAfter(now());
        }
        $schedule->save();

        $first = $schedule->next_run_at->timezone(config('app.display_timezone'))->format('D j M, g:i A');
        AdminActivity::record('notification.scheduled',
            "scheduled “{$title}” for " . ($target ? "user #{$target->id}" : 'all users') . ', ' . lcfirst($schedule->describe()) . ', by ' . implode(' and ', $channels),
            $schedule);

        return redirect()->route('admin.notifications.index', ['tab' => 'scheduled'])
            ->with('success', "Scheduled: {$schedule->describe()}. " . ($schedule->repeat === 'once' ? "It goes on {$first}." : "The first one goes on {$first}."));
    }

    /** Pause a schedule, or resume it from its next time after now. */
    public function toggle(ScheduledNotification $scheduled)
    {
        if ($scheduled->active) {
            $scheduled->update(['active' => false]);
            $verb = 'paused';
        } else {
            $next = $scheduled->repeat === 'once' ? $scheduled->next_run_at : $scheduled->nextRunAfter(now());
            if (! $next || $next->lte(now())) {
                return back()->with('error', 'That one-off time has passed. Delete it and schedule a new one.');
            }
            $scheduled->update(['active' => true, 'next_run_at' => $next]);
            $verb = 'resumed';
        }
        AdminActivity::record("notification.schedule_{$verb}", "{$verb} the schedule “{$scheduled->title}”", $scheduled);

        return redirect()->route('admin.notifications.index', ['tab' => 'scheduled'])
            ->with('success', ucfirst($verb) . ": “{$scheduled->title}”.");
    }

    public function destroy(ScheduledNotification $scheduled)
    {
        AdminActivity::record('notification.schedule_deleted', "deleted the schedule “{$scheduled->title}”", $scheduled);
        $scheduled->delete();

        return redirect()->route('admin.notifications.index', ['tab' => 'scheduled'])
            ->with('success', "Deleted the schedule “{$scheduled->title}”. Nothing more will go out from it.");
    }
}
