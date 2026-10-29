<?php

namespace App\Http\Controllers\Tracker;

use App\Http\Controllers\Controller;
use App\Support\DisplayCurrency;
use App\Support\Labels;
use App\Support\MonthBudgets;
use App\Support\Reports;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** The app's Analytics tab. */
class AnalyticsController extends Controller
{
    /** How many periods the trend line covers in each range. */
    private const TREND_COUNTS = ['weekly' => 12, 'monthly' => 12, 'yearly' => 5];

    public function index(Request $request)
    {
        $user  = $request->user();
        $uid   = $user->id;
        $money = DisplayCurrency::for($user);
        $ranges = TransactionController::RANGES;
        $pick  = fn (string $key) => array_key_exists($request->query($key), $ranges) ? $request->query($key) : 'monthly';

        $range = $pick('range');
        $flow  = Reports::flow($uid, $range, 6, Labels::now());

        $trendRange = $pick('trend');
        $trend = Reports::flow($uid, $trendRange, self::TREND_COUNTS[$trendRange], Labels::now());

        return view('tracker.analytics', [
            'money'  => $money,
            'ranges' => $ranges,
            'range'  => $range,
            'chart'  => [
                'labels'  => array_map(fn ($b) => TransactionController::bucketLabel($range, $b['start']), $flow),
                'income'  => array_column($flow, 'income_minor'),
                'expense' => array_column($flow, 'expense_minor'),
            ],
            'spent'    => array_sum(array_column($flow, 'expense_minor')),
            'received' => array_sum(array_column($flow, 'income_minor')),
            'trendRange' => $trendRange,
            'trend' => [
                'labels'   => array_map(fn ($b) => TransactionController::bucketLabel($trendRange, $b['start']), $trend),
                'captions' => array_map(fn ($b) => self::caption($trendRange, $b['start']), $trend),
                'income'   => array_column($trend, 'income_minor'),
                'expense'  => array_column($trend, 'expense_minor'),
            ],
            'trendEmpty' => array_sum(array_column($trend, 'income_minor')) + array_sum(array_column($trend, 'expense_minor')) === 0,
            'budgets'  => self::monthlyBudgets($uid, Labels::now()->format('Y-m'), $money),
            'history'  => array_map(fn ($d) => $d + ['label' => Labels::dayHeading(substr($d['day'], 0, 10))], Reports::dayFlow($uid, 8)),
        ]);
    }

    /** "October 2026", "Week of 12 Jul" or "2026" — the trend read-out's heading. */
    private static function caption(string $range, string $start): string
    {
        $date = Carbon::parse($start);
        return match ($range) {
            'weekly'  => 'Week of ' . $date->format('j M'),
            'monthly' => $date->format('F Y'),
            default   => $date->format('Y'),
        };
    }

    /**
     * The Monthly Budgets card: the whole month first when it has a budget,
     * then each category with a budget in force, the most used first.
     */
    public static function monthlyBudgets(int $uid, string $month, DisplayCurrency $money): array
    {
        $totals  = Reports::categoryTotals($uid, $month);
        $budgets = MonthBudgets::load($uid, $month);
        $rows    = [];

        if ($budgets->wholeMinor() !== null) {
            $spent = (int) $totals->sum('total_minor');
            $rows[] = ['name' => 'Whole month', 'icon' => '👛', 'color' => '#6366F1'] + self::usage($money, $spent, $budgets->wholeMinor());
        }

        $categories = [];
        foreach ($totals as $row) {
            $budget = $budgets->inForce($row['category_id'], $row['budget_minor']);
            if ($budget === null) continue;
            $categories[] = ['name' => $row['category_name'], 'icon' => $row['category_icon'], 'color' => $row['category_color']]
                + self::usage($money, $row['total_minor'], $budget);
        }
        usort($categories, fn ($a, $b) => $b['usage']['ratio'] <=> $a['usage']['ratio']);

        return array_merge($rows, $categories);
    }

    private static function usage(DisplayCurrency $money, int $spent, int $budget): array
    {
        $usage = $money->budgetUsage($spent, $budget);
        return ['usage' => $usage, 'percent' => (int) round($usage['ratio'] * 100)];
    }
}
