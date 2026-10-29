<?php

namespace App\Http\Controllers\Tracker;

use App\Http\Controllers\Controller;
use App\Models\AppExpense;
use App\Models\Category;
use App\Support\DisplayCurrency;
use App\Support\SidebarColors;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The app's Settings tab, Appearance and About. */
class SettingsController extends Controller
{
    public const THEMES = ['light' => 'Light', 'dark' => 'Dark', 'system' => 'System'];

    public function index(Request $request)
    {
        $user  = $request->user();
        $money = DisplayCurrency::for($user);
        $uid   = $user->id;

        return view('tracker.settings', [
            'user'       => $user,
            'theme'      => self::THEMES[$user->theme_mode] ?? 'Light',
            'currency'   => "{$money->symbol} {$money->code}",
            'categories' => Category::where('user_id', $uid)->count(),
            'budgets'    => Category::where('user_id', $uid)->whereNotNull('budget_minor')->count(),
        ]);
    }

    public function appearance(Request $request)
    {
        return view('tracker.appearance', ['theme' => $request->user()->theme_mode ?: 'light']);
    }

    /** Settings › Reminders: the daily push, budget alert pushes and the monthly summary email. */
    public function reminders(Request $request)
    {
        return view('tracker.reminders', ['user' => $request->user()]);
    }

    public function updateTheme(Request $request)
    {
        $request->validate(['theme_mode' => 'required|in:light,dark,system']);
        $request->user()->update(['theme_mode' => $request->theme_mode]);

        return redirect()->route('settings.appearance');
    }

    /** The sidebar colour, picked from Appearance or from the admin Settings page. */
    public function updateSidebar(Request $request)
    {
        $request->validate(['sidebar_color' => ['required', Rule::in(array_keys(SidebarColors::ALL))]]);
        $request->user()->update(['sidebar_color' => $request->sidebar_color]);

        return redirect()->back(302, [], route('settings.appearance'))
            ->with('success', 'Sidebar colour set to ' . SidebarColors::ALL[$request->sidebar_color][0] . '.');
    }

    /**
     * Settings › Reminders: the evening reminder's time (or off), budget
     * alert pushes and the monthly summary email. Each form sends one field.
     */
    public function updateReminders(Request $request)
    {
        $request->user()->update(self::reminderChanges($request));

        return back()->with('success', 'Reminders saved');
    }

    /** The reminder fields a request carries, validated; the API uses this too. */
    public static function reminderChanges(Request $request): array
    {
        $request->validate([
            'daily_reminder_at' => 'sometimes|nullable|date_format:H:i',
            'budget_alert_push' => 'sometimes|boolean',
            'monthly_summary'   => 'sometimes|boolean',
        ], ['daily_reminder_at.date_format' => 'Pick a time for the reminder.']);

        $changes = [];
        if ($request->has('daily_reminder_at')) $changes['daily_reminder_at'] = $request->input('daily_reminder_at') ?: null;
        foreach (['budget_alert_push', 'monthly_summary'] as $field) {
            if ($request->has($field)) $changes[$field] = $request->boolean($field);
        }

        return $changes;
    }

    /** The per-user switch for the activity emails. */
    public function updateEmailNotifications(Request $request)
    {
        $request->validate(['enabled' => 'required|boolean']);
        $request->user()->update(['email_notifications' => $request->boolean('enabled')]);

        return redirect()->route('settings.index')
            ->with('success', $request->boolean('enabled') ? 'Email notifications turned on' : 'Email notifications turned off');
    }

    public function about(Request $request)
    {
        $uid = $request->user()->id;

        return view('tracker.about', [
            'expenses'   => AppExpense::where('user_id', $uid)->count(),
            'categories' => Category::where('user_id', $uid)->count(),
        ]);
    }
}
