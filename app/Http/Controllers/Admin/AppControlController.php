<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminActivity;
use App\Support\AppControl;
use App\Support\AppSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Admin › App control: maintenance mode, the app versions the API accepts,
 * and email verification in the app (App\Support\AppControl). Every change
 * is logged.
 */
class AppControlController extends Controller
{
    public function index()
    {
        return view('admin.app-control', [
            'maintenance'        => AppControl::maintenance(),
            'maintenanceMessage' => AppControl::customMaintenanceMessage(),
            'minVersion'         => AppControl::minVersion(),
            'latestVersion'      => AppControl::latestVersion(),
            'updateMessage'      => AppControl::updateMessage(),
            'emailRequired'      => AppControl::emailVerificationRequired(),
            'status'             => AppControl::status(),
            'reminderAt'         => AppControl::dailyReminderAt(),
            'savedReminderAt'    => AppSettings::get(AppControl::DAILY_REMINDER_AT, AppControl::DEFAULT_REMINDER_AT),
            'autoMessages'       => [
                AppControl::BUDGET_ALERT_PUSH => AppControl::budgetAlertPush(),
                AppControl::MONTHLY_SUMMARY   => AppControl::monthlySummary(),
                AppControl::BIRTHDAY_WISHES   => AppControl::birthdayWishes(),
            ],
        ]);
    }

    /** What each automatic message is called in the activity log and the success message. */
    private const AUTO_MESSAGES = [
        AppControl::DAILY_REMINDER    => 'the daily reminder',
        AppControl::BUDGET_ALERT_PUSH => 'budget alert pushes',
        AppControl::MONTHLY_SUMMARY   => 'the monthly summary email',
        AppControl::BIRTHDAY_WISHES   => 'birthday wishes',
    ];

    /**
     * The messages the server sends everyone on its own. One field per form:
     * a switch (1 or 0) or the daily reminder's time. Users cannot turn them
     * off; only this can.
     */
    public function reminders(Request $request)
    {
        $request->validate([
            AppControl::DAILY_REMINDER    => 'sometimes|boolean',
            AppControl::DAILY_REMINDER_AT => 'sometimes|date_format:H:i',
            AppControl::BUDGET_ALERT_PUSH => 'sometimes|boolean',
            AppControl::MONTHLY_SUMMARY   => 'sometimes|boolean',
            AppControl::BIRTHDAY_WISHES   => 'sometimes|boolean',
        ], [AppControl::DAILY_REMINDER_AT . '.date_format' => 'Pick a time for the daily reminder.']);

        $back = redirect()->route('admin.app-control');

        if ($request->has(AppControl::DAILY_REMINDER_AT)) {
            $time = $request->input(AppControl::DAILY_REMINDER_AT);
            AppSettings::set(AppControl::DAILY_REMINDER_AT, $time === AppControl::DEFAULT_REMINDER_AT ? null : $time);
            $label = \Carbon\Carbon::createFromFormat('H:i', $time)->format('g:i A');
            AdminActivity::record('app.daily_reminder_at', "set the daily reminder to {$label}");

            return $back->with('success', "The daily reminder now goes out at {$label}.");
        }

        foreach (self::AUTO_MESSAGES as $key => $name) {
            if (! $request->has($key)) continue;
            $on = $request->boolean($key);
            // On is the default: stored only while off.
            AppSettings::set($key, $on ? null : false);
            AdminActivity::record("app.{$key}_" . ($on ? 'on' : 'off'), ($on ? 'turned on ' : 'turned off ') . $name);

            return $back->with('success', ucfirst($name) . ($on ? ' will go out again.' : ' will not go out to anyone.'));
        }

        return $back;
    }

    /**
     * Either field alone: `enabled` (the switch) and/or `message` (the
     * words people see). Leaving the message empty uses the default.
     */
    public function maintenance(Request $request)
    {
        $request->validate([
            'enabled' => 'sometimes|boolean',
            'message' => 'sometimes|nullable|string|max:300',
        ], ['message.max' => 'Keep the message to 300 characters.']);

        $changes = [];
        if ($request->has('message')) {
            $message = trim((string) $request->input('message'));
            if ($message !== (string) AppControl::customMaintenanceMessage()) {
                AppSettings::set(AppControl::MAINTENANCE_MESSAGE, $message === '' ? null : $message);
                $changes[] = 'message';
                AdminActivity::record('app.maintenance_message', $message === ''
                    ? 'reset the maintenance message to the default'
                    : 'changed the maintenance message');
            }
        }
        if ($request->has('enabled') && $request->boolean('enabled') !== AppControl::maintenance()) {
            $on = $request->boolean('enabled');
            AppSettings::set(AppControl::MAINTENANCE, $on ?: null);
            $changes[] = 'switch';
            AdminActivity::record($on ? 'app.maintenance_on' : 'app.maintenance_off',
                $on ? 'turned maintenance mode on' : 'turned maintenance mode off');
        }

        // Back where the switch was: App control, or the dashboard's quick actions.
        $back = redirect()->back(302, [], route('admin.app-control'));
        if (! $changes) return $back;

        return $back->with('success', match (true) {
            in_array('switch', $changes, true) && AppControl::maintenance() => 'Maintenance mode is on. Everyone but admins sees the maintenance message.',
            in_array('switch', $changes, true) => 'Maintenance mode is off. The app and the website are open again.',
            default => 'Maintenance message saved.',
        });
    }

    public function versions(Request $request)
    {
        $request->merge([
            'min_version'    => trim((string) $request->input('min_version')),
            'latest_version' => trim((string) $request->input('latest_version')),
        ]);
        $request->validate([
            'min_version'    => ['nullable', 'max:20', 'regex:' . AppControl::VERSION_PATTERN],
            'latest_version' => ['nullable', 'max:20', 'regex:' . AppControl::VERSION_PATTERN],
            'update_message' => 'nullable|string|max:300',
        ], [
            'min_version.*'      => 'Write a version like 1.2.0, or leave it empty.',
            'latest_version.*'   => 'Write a version like 1.3.0, or leave it empty.',
            'update_message.max' => 'Keep the message to 300 characters.',
        ]);

        $min = $request->input('min_version') ?: null;
        $latest = $request->input('latest_version') ?: null;
        $message = trim((string) $request->input('update_message')) ?: null;
        if ($min && $latest && AppControl::compare($latest, $min) < 0) {
            throw ValidationException::withMessages(['latest_version' => 'The latest version cannot be older than the minimum.']);
        }

        $before = [AppControl::minVersion(), AppControl::latestVersion(), AppControl::updateMessage()];
        AppSettings::set(AppControl::MIN_VERSION, $min);
        AppSettings::set(AppControl::LATEST_VERSION, $latest);
        AppSettings::set(AppControl::UPDATE_MESSAGE, $message);

        $notes = [];
        if ($before[0] !== $min) $notes[] = $min ? "set the minimum app version to {$min}" : 'removed the minimum app version';
        if ($before[1] !== $latest) $notes[] = $latest ? "set the latest app version to {$latest}" : 'removed the latest app version';
        if ($before[2] !== $message) $notes[] = $message ? 'changed the update message' : 'removed the update message';
        if ($notes) AdminActivity::record('app.versions', implode(', ', $notes));

        return redirect()->route('admin.app-control')->with('success', $min
            ? "Saved. Apps older than {$min} must update before they can carry on."
            : 'Saved. No app version is blocked.');
    }

    /** `required`: 1 or 0 — or "default" to follow EMAIL_VERIFICATION_REQUIRED_FOR_API again. */
    public function emailVerification(Request $request)
    {
        $request->validate(['required' => 'required|in:0,1,default']);
        $before = AppControl::emailVerificationRequired();

        $value = $request->input('required');
        AppSettings::set(AppControl::EMAIL_VERIFICATION, $value === 'default' ? null : $value === '1');
        $now = AppControl::emailVerificationRequired();

        AdminActivity::record('app.email_verification', match (true) {
            $value === 'default' => 'set email verification in the app back to the server default (' . ($now ? 'required' : 'not required') . ')',
            $now => 'made email verification required in the app',
            default => 'made email verification optional in the app',
        });

        return redirect()->route('admin.app-control')->with('success', $now
            ? 'The app now asks for a verified email before anything else.' . ($before ? '' : ' People who have not verified yet get a code to type in.')
            : 'The app no longer needs a verified email to carry on.');
    }
}
