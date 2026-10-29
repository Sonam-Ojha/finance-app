<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AdminActivity;
use App\Support\SignOut;
use Illuminate\Http\Request;

/** Admin › Users: everyone with an account, and who is an admin. */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        // "unverified": who has not confirmed their email (the dashboard links here).
        // "blocked": accounts blocked here.
        $status = in_array($request->query('status'), ['unverified', 'blocked'], true) ? $request->query('status') : null;

        $users = User::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('email', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->when($status === 'unverified', fn ($query) => $query->whereNull('email_verified_at'))
            ->when($status === 'blocked', fn ($query) => $query->whereNotNull('blocked_at'))
            ->withCount('deviceTokens')
            ->orderByRaw("CASE WHEN role = 'admin' THEN 0 ELSE 1 END")
            ->orderBy('email')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users'      => $users,
            'search'     => $search,
            'status'     => $status,
            'adminCount' => User::where('role', User::ROLE_ADMIN)->count(),
        ]);
    }

    public function updateRole(Request $request, User $user)
    {
        $request->validate(['role' => 'required|in:' . User::ROLE_USER . ',' . User::ROLE_ADMIN]);
        $role = $request->input('role');

        if ($role === User::ROLE_ADMIN && ! $user->isAdmin() && $user->isBlocked()) {
            return back()->with('error', "{$user->email} is blocked. Unblock them first.");
        }

        if ($role === User::ROLE_USER && $user->isAdmin()) {
            // Locking yourself out, or leaving nobody who can make admins.
            if ($user->is($request->user())) {
                return back()->with('error', 'You cannot remove your own admin access. Ask another admin to do it.');
            }
            if (User::where('role', User::ROLE_ADMIN)->count() <= 1) {
                return back()->with('error', 'There must always be at least one admin.');
            }
        }

        $wasAdmin = $user->isAdmin();
        $user->forceFill(['role' => $role])->save();
        if ($wasAdmin !== $user->isAdmin()) {
            AdminActivity::record('user.role_changed', $user->isAdmin()
                ? "made user #{$user->id} an admin"
                : "removed admin access from user #{$user->id}", $user);
        }

        return back()->with('success', $role === User::ROLE_ADMIN
            ? "{$user->email} is now an admin."
            : "{$user->email} is no longer an admin.");
    }

    /**
     * Blocks an account: no more signing in, and every session it has ends
     * now — the app's tokens, its phones' pushes, the website's sessions.
     */
    public function block(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot block yourself.');
        }
        if ($user->isAdmin()) {
            return back()->with('error', "{$user->email} is an admin. Remove their admin access first.");
        }
        if ($user->isBlocked()) {
            return back()->with('success', "{$user->email} is already blocked.");
        }

        $user->forceFill(['blocked_at' => now()])->save();
        SignOut::everywhere($user);
        AdminActivity::record('user.blocked', "blocked user #{$user->id}", $user);

        return back()->with('success', "{$user->email} is blocked and signed out everywhere.");
    }

    public function unblock(User $user)
    {
        if (! $user->isBlocked()) {
            return back()->with('success', "{$user->email} is not blocked.");
        }

        $user->forceFill(['blocked_at' => null])->save();
        AdminActivity::record('user.unblocked', "unblocked user #{$user->id}", $user);

        return back()->with('success', "{$user->email} can sign in again.");
    }
}
