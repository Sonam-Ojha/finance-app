<?php

namespace App\Http\Middleware;

use App\Support\AppControl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every API request passes here first (the `api` group):
 *
 *  - maintenance mode on (Admin › App control): 503 `{message, code: "maintenance"}`;
 *  - an app older than the minimum version (its X-App-Version header): 426
 *    `{message, code: "update_required"}`.
 *
 * GET /api/v1/app-status always answers, so the app can find out why. An
 * outdated app may still ask who is signed in, and sign out.
 */
class AppGate
{
    private const ALWAYS_OPEN = ['api/v1/app-status'];

    private const OPEN_WHEN_OUTDATED = ['api/v1/me', 'api/v1/logout', 'api/v1/me/devices'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is(...self::ALWAYS_OPEN)) return $next($request);

        if (AppControl::maintenance()) {
            return response()->json([
                'message' => AppControl::maintenanceMessage(),
                'code'    => 'maintenance',
            ], 503, ['Retry-After' => '300']);
        }

        if (! $request->is(...self::OPEN_WHEN_OUTDATED) && AppControl::isOutdated($request->header('X-App-Version'))) {
            return response()->json([
                'message' => AppControl::updateMessage() ?: AppControl::DEFAULT_UPDATE_MESSAGE,
                'code'    => 'update_required',
            ], 426);
        }

        return $next($request);
    }
}
