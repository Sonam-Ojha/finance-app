<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Labels;
use App\Support\MonthReport;
use App\Support\Reports;
use App\Support\TransactionCsv;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Files to download: the transactions as CSV, and a month's report as PDF. */
class ExportController extends Controller
{
    /**
     * GET /export/transactions.csv?from=YYYY-MM-DD&to=YYYY-MM-DD. With neither,
     * the current month (India time); with one, the range is open at the other end.
     */
    public function transactions(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to'   => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), 'after_or_equal:from')],
        ], [
            'from.date_format' => 'Pick a start date like 2026-10-01.',
            'to.date_format'   => 'Pick an end date like 2026-10-31.',
            'to.after_or_equal' => 'The end date cannot be before the start date.',
        ]);

        $from = $request->input('from');
        $to   = $request->input('to');
        if (! $from && ! $to) {
            [$from] = Reports::monthRange(Labels::now()->format('Y-m'));
            $to = Carbon::parse($from)->endOfMonth()->toDateString();
        }

        return TransactionCsv::download(
            $request->user()->id,
            ['date_from' => $from, 'date_to' => $to, 'sort' => 'newest'],
            TransactionCsv::filename($from, $to),
        );
    }

    /** GET /export/report.pdf?month=YYYY-MM — the current month (India time) when left out. */
    public function report(Request $request)
    {
        $request->validate(
            ['month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']],
            ['month.regex' => 'Pick a month like 2026-10.'],
        );

        return MonthReport::download($request->user(), $request->input('month') ?: Labels::now()->format('Y-m'));
    }
}
