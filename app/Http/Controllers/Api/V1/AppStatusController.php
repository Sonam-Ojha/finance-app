<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\AppControl;

/**
 * GET /api/v1/app-status — public, and answered even in maintenance mode
 * (App\Http\Middleware\AppGate). The app reads it on launch:
 *
 *   { data: { maintenance, maintenance_message, min_version, latest_version,
 *             update_message, email_verification_required } }
 */
class AppStatusController extends Controller
{
    public function show()
    {
        return response()->json(['data' => AppControl::status()])
            ->header('Cache-Control', 'no-store');
    }
}
