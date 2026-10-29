<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * An account Google or Apple sign-in just made has no date of birth
 * (users.birthday_required). On the website it gives one first, on the
 * "Complete your profile" page — it can still sign out or verify its email
 * meanwhile. Appended to the `web` group (bootstrap/app.php); the app gates
 * on the flag itself.
 */
class RequireBirthday
{
    private const OPEN = ['profile.complete', 'profile.complete.update', 'logout', 'verification.*'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();
        if (! $user || ! $user->birthday_required || $request->routeIs(...self::OPEN)) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Add your date of birth to carry on.', 'code' => 'birthday_required'], 409)
            : redirect()->route('profile.complete');
    }
}
