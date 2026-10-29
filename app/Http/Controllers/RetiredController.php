<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * What is left of the older "Smart Paisa" site: its pages and its first
 * API (before /api/v1) are retired. Their data stays in the old tables
 * (expenses, incomes, bank_accounts, …); the expenses and incomes were
 * copied into the app's tables by 2026_10_28_000001.
 *
 *  - Old pages (routes/web.php) answer with a permanent redirect to the
 *    page that does their job now, named by the route default `to`
 *    (plus an optional `query` array).
 *  - The old API (routes/api.php) answers 410 Gone, so an outdated app can
 *    tell its user to update.
 */
class RetiredController extends Controller
{
    public const API_MESSAGE = 'This version of the Expense Tracker API has been retired. Please update the app.';

    public function api(): JsonResponse
    {
        return response()->json(['message' => self::API_MESSAGE, 'code' => 'api_retired'], 410);
    }

    public function page(Request $request): RedirectResponse
    {
        $route = $request->route();
        $to    = (string) $route->defaults['to'];
        $query = (array) ($route->defaults['query'] ?? []);

        // The old report form sent a month and a year: open that month's report.
        if ($to === 'reports.pdf') {
            $year  = (int) $request->input('year');
            $month = (int) $request->input('month');
            if ($year >= 2000 && $year <= 2100 && $month >= 1 && $month <= 12) {
                $query['month'] = sprintf('%04d-%02d', $year, $month);
            }
        }

        return redirect()->to(route($to, $query), 301);
    }
}
