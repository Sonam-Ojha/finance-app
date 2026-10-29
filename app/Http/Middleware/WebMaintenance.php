<?php

namespace App\Http\Middleware;

use App\Support\AppControl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Maintenance mode on the website (the `web` group): everyone but admins
 * sees the maintenance page, with a 503. Signing in and out stays open —
 * otherwise no admin could get back in to switch it off.
 */
class WebMaintenance
{
    /** Route names that stay open: signing in (password or Google, and the two-factor step) and out. */
    private const OPEN_ROUTES = ['login', 'logout', 'auth.google', '*two-factor*', '*two_factor*', '*2fa*'];

    /** The same by path, for routes without a name. */
    private const OPEN_PATHS = ['login', 'logout', 'auth/google', 'two-factor*', '*/two-factor*', '2fa*', '*/2fa*'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! AppControl::maintenance()) return $next($request);
        if ($request->user()?->isAdmin()) return $next($request);
        if ($request->routeIs(...self::OPEN_ROUTES) || $request->is(...self::OPEN_PATHS)) return $next($request);

        $message = AppControl::maintenanceMessage();
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'code' => 'maintenance'], 503, ['Retry-After' => '300']);
        }

        return response()->view('maintenance', ['message' => $message, 'user' => $request->user()], 503, ['Retry-After' => '300']);
    }
}
