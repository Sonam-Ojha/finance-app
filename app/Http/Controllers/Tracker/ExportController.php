<?php

namespace App\Http\Controllers\Tracker;

use App\Http\Controllers\Controller;
use App\Support\DisplayCurrency;
use App\Support\Labels;
use App\Support\MonthReport;
use App\Support\TransactionCsv;
use Illuminate\Http\Request;

/** The Transactions page's Export menu: the list as CSV, and a month's report as PDF. */
class ExportController extends Controller
{
    /** The list exactly as the Transactions page shows it with the same query string — every page of it. */
    public function transactions(Request $request, TransactionController $list)
    {
        $user  = $request->user();
        $month = Labels::clampMonth($request->query('month'));
        [$filters] = $list->filters($request, DisplayCurrency::for($user), CategoryController::forUser($user->id), $month);
        $query = $filters['query'];

        return TransactionCsv::download($user->id, $query, TransactionCsv::filename($query['date_from'], $query['date_to']));
    }

    public function report(Request $request)
    {
        return MonthReport::download($request->user(), Labels::clampMonth($request->query('month')));
    }
}
