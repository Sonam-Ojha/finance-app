<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A blocked account (Admin › Users › Block) gets nothing more. On every web
 * request its session is ended and it lands on the sign-in page; on every
 * API request with its token the answer is 403 `account_blocked`.
 * Appended to the `web` group, and to the `api` group with the `sanctum`
 * guard (bootstrap/app.php).
 */
class RejectBlockedUsers
{
    public const MESSAGE = 'This account is blocked. Contact support.';

    public function handle(Request $request, Closure $next, string $guard = 'web'): Response
    {
        $user = Auth::guard($guard)->user();
        if (! $user || ! method_exists($user, 'isBlocked') || ! $user->isBlocked()) {
            return $next($request);
        }

        if ($guard !== 'web') {
            return self::response();
        }

        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $request->expectsJson()
            ? self::response()
            : redirect()->route('login')->withErrors(['email' => self::MESSAGE]);
    }

    /** 403 `{message, code: "account_blocked"}` — what the app shows and then signs out on. */
    public static function response(): JsonResponse
    {
        return response()->json(['message' => self::MESSAGE, 'code' => 'account_blocked'], 403);
    }
}
