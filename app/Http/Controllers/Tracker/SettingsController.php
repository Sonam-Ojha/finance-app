<?php

namespace App\Http\Controllers\Tracker;

use App\Http\Controllers\Controller;
use App\Models\AppExpense;
use App\Models\Category;
use App\Support\DisplayCurrency;
use Illuminate\Http\Request;

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

    public function updateTheme(Request $request)
    {
        $request->validate(['theme_mode' => 'required|in:light,dark,system']);
        $request->user()->update(['theme_mode' => $request->theme_mode]);

        return redirect()->route('settings.appearance');
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
