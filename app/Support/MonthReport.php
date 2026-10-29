<?php

namespace App\Support;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Response;

/**
 * One month on paper: money in, spent and saved, where it went, how each
 * budget did, and every entry. The website's "Monthly report PDF" and
 * GET /api/v1/export/report.pdf both download this.
 */
class MonthReport
{
    public static function download(User $user, string $month): Response
    {
        $pdf = Pdf::loadView('exports.month-report', self::data($user, $month))
            ->setPaper('a4')
            // Only the glyphs used, not the whole font: ~60 KB instead of ~900 KB.
            ->setOption('isFontSubsettingEnabled', true)
            ->output();

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . self::filename($month) . '"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }

    public static function filename(string $month): string
    {
        return "expense-report-{$month}.pdf";
    }

    /** Everything the report shows, already worded and formatted in the user's currency. */
    public static function data(User $user, string $month): array
    {
        $uid      = $user->id;
        $money    = DisplayCurrency::for($user);
        [$start]  = Reports::monthRange($month);
        $last     = Carbon::parse($start)->endOfMonth()->toDateString();
        $totals   = Reports::categoryTotals($uid, $month);
        $budgets  = MonthBudgets::load($uid, $month);
        $spent    = (int) $totals->sum('total_minor');
        $received = Reports::receivedInMonth($uid, $month);
        $saved    = $received - $spent;

        $breakdown = $totals->filter(fn ($c) => $c['total_minor'] > 0)->values()->map(fn ($c) => [
            'name'   => $c['category_name'],
            'color'  => $c['category_color'],
            'amount' => $money->format($c['total_minor']),
            'count'  => $c['count'],
            'share'  => $spent > 0 ? round($c['total_minor'] / $spent * 100, 1) : 0.0,
        ]);

        $budgetRows = [];
        $whole = $budgets->wholeMinor();
        if ($whole > 0) $budgetRows[] = self::budgetRow($money, 'Whole month', '#002e62', $spent, $whole);
        foreach ($totals as $c) {
            $budget = $budgets->inForce($c['category_id'], $c['budget_minor']);
            if ($budget > 0) $budgetRows[] = self::budgetRow($money, $c['category_name'], $c['category_color'], $c['total_minor'], $budget);
        }

        $entries = TransactionSearch::all($uid, ['date_from' => $start, 'date_to' => $last, 'sort' => 'oldest'])
            ->map(fn (array $row) => [
                'date'    => Carbon::parse($row['date'])->format('j M'),
                'income'  => $row['type'] === 'income',
                'label'   => $row['type'] === 'income' ? $row['source'] : $row['category_name'],
                'color'   => $row['type'] === 'income' ? '#10B981' : $row['category_color'],
                'note'    => $row['note'],
                'payment' => PaymentMethods::label($row['payment_method']),
                'amount'  => ($row['type'] === 'income' ? '+' : '−') . $money->format($row['amount_minor']),
            ]);

        return [
            'user'       => $user,
            'month'      => $month,
            'monthLabel' => Labels::month($month),
            'generated'  => Labels::now()->format('j M Y, g:i A'),
            'currency'   => $money->label,
            'received'   => $money->format($received),
            'spent'      => $money->format($spent),
            'saved'      => $money->format(abs($saved)),
            'overspent'  => $saved < 0,
            'savingRate' => $received > 0 && $saved > 0 ? (int) round($saved / $received * 100) : null,
            'breakdown'  => $breakdown,
            'budgets'    => $budgetRows,
            'entries'    => $entries,
            'expenseCount' => $entries->where('income', false)->count(),
            'incomeCount'  => $entries->where('income', true)->count(),
        ];
    }

    private static function budgetRow(DisplayCurrency $money, string $name, ?string $color, int $spent, int $budget): array
    {
        $usage = $money->budgetUsage($spent, $budget);

        return [
            'name'      => $name,
            'color'     => $color ?: '#94A3B8',
            'budget'    => $money->format($budget),
            'spent'     => $money->format($spent),
            'remainder' => $usage['remainder'],
            'percent'   => (int) round($usage['ratio'] * 100),
            'ratio'     => min(1, $usage['ratio']),
            'status'    => $usage['over'] ? 'over' : ($usage['ratio'] >= MonthBudgets::NEAR_LIMIT ? 'near' : 'ok'),
        ];
    }
}
