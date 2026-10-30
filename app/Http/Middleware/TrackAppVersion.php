<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every authenticated API request.
 * Saves X-App-Version to the user row so the admin can see the version
 * distribution across active users.
 */
class TrackAppVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $version = $request->header('X-App-Version');
        $user    = $request->user();

        if ($version && $user && preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            if ($user->last_app_version !== $version) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'last_app_version'      => $version,
                        'app_version_updated_at' => now(),
                    ]);
            }
        }

        return $response;
    }
}
