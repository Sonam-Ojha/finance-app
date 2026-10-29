<?php

namespace App\Http\Controllers\Tracker;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\DisplayCurrency;
use App\Support\Labels;
use App\Support\PaymentMethods;
use App\Support\Reports;
use App\Support\TransactionSearch;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The app's Transactions tab: one month at a glance, the income-vs-expense
 * chart, and every expense and income searched, filtered and sorted.
 */
class TransactionController extends Controller
{
    public const RANGES = ['weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly'];

    public const SORT_LABELS = ['newest' => 'Newest', 'oldest' => 'Oldest', 'highest' => 'Highest amount', 'lowest' => 'Lowest amount'];

    public function index(Request $request)
    {
        $user  = $request->user();
        $uid   = $user->id;
        $money = DisplayCurrency::for($user);
        $month = Labels::clampMonth($request->query('month'));
        $categories = Category::where('user_id', $uid)->orderBy('sort_order')->orderBy('name')->get();

        // ── The month at a glance ──
        $totals   = Reports::categoryTotals($uid, $month);
        $received = Reports::receivedInMonth($uid, $month);
        $spent    = (int) $totals->sum('total_minor');

        // ── Income vs Expense ──
        $range  = array_key_exists($request->query('range'), self::RANGES) ? $request->query('range') : 'monthly';
        $isCurrent = $month === Labels::now()->format('Y-m');
        $anchor = $isCurrent ? Labels::now() : Carbon::parse($month . '-01')->endOfMonth();
        $flow   = Reports::flow($uid, $range, 6, $anchor);
        $highlight = match ($range) {
            'monthly' => array_search($month, array_column($flow, 'key'), true),
            'yearly'  => array_search(substr($month, 0, 4), array_column($flow, 'key'), true),
            default   => false,
        };

        // ── The list ──
        [$filters, $errors] = $this->filters($request, $money, $categories, $month);
        $page = TransactionSearch::paginate($uid, $filters['query'], 50, max(1, (int) $request->query('page', 1)))
            ->withQueryString();

        $byDay = in_array($filters['sort'], ['newest', 'oldest'], true);
        $groups = $byDay
            ? collect($page->items())->groupBy('date')->map(fn ($rows, $day) => [
                'label'    => Labels::dayGroupLabel($day),
                'received' => $rows->where('type', 'income')->sum('amount_minor'),
                'spent'    => $rows->where('type', 'expense')->sum('amount_minor'),
                'rows'     => $rows,
            ])->values()
            : collect([[
                'label' => $filters['sort'] === 'highest' ? 'Highest amount' : 'Lowest amount',
                'received' => 0, 'spent' => 0, 'rows' => collect($page->items()),
            ]]);

        return view('tracker.transactions', [
            'money'      => $money,
            'month'      => $month,
            'monthLabel' => Labels::month($month),
            'isCurrent'  => $isCurrent,
            'categories' => $categories,
            'received'   => $received,
            'spent'      => $spent,
            'breakdown'  => Reports::slices($totals),
            'range'      => $range,
            'chart'      => [
                'labels'    => array_map(fn ($b) => self::bucketLabel($range, $b['start']), $flow),
                'income'    => array_column($flow, 'income_minor'),
                'expense'   => array_column($flow, 'expense_minor'),
                'highlight' => $highlight === false ? -1 : $highlight,
            ],
            'filters'    => $filters,
            'filterErrors' => $errors,
            'page'       => $page,
            'groups'     => $page->isEmpty() ? collect() : $groups,
            'byDay'      => $byDay,
            'monthStart' => $month . '-01',
            'monthEnd'   => min(Carbon::parse($month . '-01')->endOfMonth()->toDateString(), Labels::now()->toDateString()),
        ]);
    }

    /** "12 Jul" a week, "Oct" a month, "2026" a year — the chart's column labels. */
    public static function bucketLabel(string $range, string $start): string
    {
        $date = Carbon::parse($start);
        return match ($range) {
            'weekly'  => $date->format('j M'),
            'monthly' => $date->format('M'),
            default   => $date->format('Y'),
        };
    }

    /**
     * The list's filters from the query string. Bad values are dropped with
     * the message the app's filter sheet shows, rather than failing the page.
     * Also read by the CSV export (ExportController), so the file matches the list.
     */
    public function filters(Request $request, DisplayCurrency $money, $categories, string $month): array
    {
        $errors = [];
        $today  = Labels::now()->toDateString();

        $category = $categories->firstWhere('id', (int) $request->query('category'));
        $type     = in_array($request->query('type'), ['expense', 'income'], true) ? $request->query('type') : null;
        // A category only applies to expenses.
        if ($category) $type = 'expense';

        $payment = in_array($request->query('payment'), PaymentMethods::all(), true) ? $request->query('payment') : null;
        $sort    = array_key_exists($request->query('sort'), TransactionSearch::SORTS) ? $request->query('sort') : 'newest';

        $minText = trim((string) $request->query('min'));
        $maxText = trim((string) $request->query('max'));
        $min = $minText === '' ? null : $money->parse($minText);
        $max = $maxText === '' ? null : $money->parse($maxText);
        if (($minText !== '' && $min === null) || ($maxText !== '' && $max === null)) {
            $errors[] = 'Amounts need a number greater than zero, or an empty field.';
            $min = $max = null;
        } elseif ($min !== null && $max !== null && $min > $max) {
            $errors[] = 'The minimum is higher than the maximum.';
            $min = $max = null;
        }

        $date = fn ($value) => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) ? min($value, $today) : null;
        $from = $date($request->query('from'));
        $to   = $date($request->query('to'));
        if ($from && $to && $from > $to) {
            $errors[] = 'The start date is after the end date.';
            $from = $to = null;
        }
        // Without a range of its own the list shows the month on the switcher.
        $customDates = $from !== null || $to !== null;
        [$monthStart] = Reports::monthRange($month);
        $monthEnd = Carbon::parse($monthStart)->endOfMonth()->toDateString();

        $search = trim((string) $request->query('search'));

        $chips = [];
        if ($category) $chips['category'] = "{$category->icon} {$category->name}";
        if ($customDates) {
            $chips['dates'] = ($from ? Carbon::parse($from)->format('j M') : '…') . ' – ' . ($to ? Carbon::parse($to)->format('j M') : '…');
        }
        if ($payment) $chips['payment'] = PaymentMethods::label($payment);
        if ($min !== null || $max !== null) {
            $chips['amount'] = match (true) {
                $min !== null && $max !== null => $money->format($min) . ' – ' . $money->format($max),
                $min !== null                   => $money->format($min) . ' or more',
                default                         => 'Up to ' . $money->format($max),
            };
        }
        if ($sort !== 'newest') $chips['sort'] = self::SORT_LABELS[$sort];

        return [[
            'search'   => $search,
            'find'     => $request->boolean('find'),
            'type'     => $type,
            'category' => $category,
            'payment'  => $payment,
            'sort'     => $sort,
            'min'      => $min !== null ? $minText : '',
            'max'      => $max !== null ? $maxText : '',
            'from'     => $from,
            'to'       => $to,
            'custom_dates' => $customDates,
            'chips'    => $chips,
            // The badge on the filter button: amount bounds count as one.
            'count'    => count($chips),
            'query'    => [
                'search'           => $search,
                'type'             => $type,
                'category_id'      => $category?->id,
                'payment_method'   => $payment,
                'date_from'        => $customDates ? $from : $monthStart,
                'date_to'          => $customDates ? $to : $monthEnd,
                'min_amount_minor' => $min,
                'max_amount_minor' => $max,
                'sort'             => $sort,
            ],
        ], $errors];
    }
}
