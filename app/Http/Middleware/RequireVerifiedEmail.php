<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An account whose email is not verified yet can only verify it, fix it,
 * or leave. The website sends it to the code page; the API answers 403
 * `email_unverified` — once `services.email_check.required_for_api` is on.
 */
class RequireVerifiedEmail
{
    /** What the app may still call: who am I, the code, a corrected email, push, sign out, delete. */
    private const API_OPEN = ['api/v1/me', 'api/v1/me/email/*', 'api/v1/me/profile', 'api/v1/me/devices', 'api/v1/logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->email_verified_at) return $next($request);

        if ($request->is('api/*')) {
            if (! config('services.email_check.required_for_api') || $request->is(...self::API_OPEN)) {
                return $next($request);
            }

            return response()->json(['message' => 'Verify your email to carry on.', 'code' => 'email_unverified'], 403);
        }

        return redirect()->route('verification.notice');
    }
}
