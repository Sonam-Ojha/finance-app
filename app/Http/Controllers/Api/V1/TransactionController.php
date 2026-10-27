<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\PaymentMethods;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Expenses and incomes as one list, searched, filtered, sorted and paged in
 * SQL — a UNION ALL of the two tables, so only the page asked for is read.
 * `type` + `id` identify a row: the two tables share ids.
 */
class TransactionController extends Controller
{
    private const SORTS = [
        'newest'  => [['date', 'desc'], ['id', 'desc'], ['type', 'desc']],
        'oldest'  => [['date', 'asc'], ['id', 'asc'], ['type', 'asc']],
        'highest' => [['amount_minor', 'desc'], ['date', 'desc'], ['id', 'desc'], ['type', 'desc']],
        'lowest'  => [['amount_minor', 'asc'], ['date', 'desc'], ['id', 'desc'], ['type', 'desc']],
    ];

    public function index(Request $request)
    {
        $uid = $request->user()->id;
        $request->validate([
            'search'           => 'nullable|string|max:100',
            'type'             => 'nullable|in:expense,income',
            'category_id'      => ['nullable', 'integer', Rule::exists('categories', 'id')->where('user_id', $uid)],
            'payment_method'   => ['nullable', Rule::in(PaymentMethods::all())],
            'date_from'        => 'nullable|date_format:Y-m-d',
            // Compared only when there is a start: with none, Laravel would
            // read "date_from" itself as a date and reject every end date.
            'date_to'          => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('date_from'), 'after_or_equal:date_from')],
            'min_amount_minor' => 'nullable|integer|min:1',
            'max_amount_minor' => 'nullable|integer|min:1',
            'sort'             => 'nullable|in:' . implode(',', array_keys(self::SORTS)),
            'page'             => 'nullable|integer|min:1',
            'per_page'         => 'nullable|integer|min:1|max:100',
        ], [
            'category_id.exists' => 'Pick one of your own categories.',
            'payment_method.in'  => PaymentMethods::MESSAGE,
            'date_to.after_or_equal' => 'The end date cannot be before the start date.',
        ]);

        // A category only applies to expenses, so it narrows to them too.
        $type = $request->filled('category_id') ? 'expense' : $request->type;

        $parts = [];
        if ($type !== 'income') $parts[] = $this->filtered($this->expenses($uid, $request), $request, 'e.spent_at', 'e');
        if ($type !== 'expense') $parts[] = $this->filtered($this->incomes($uid, $request), $request, 'i.received_at', 'i');

        $union = array_shift($parts);
        foreach ($parts as $part) $union->unionAll($part);

        $query = DB::query()->fromSub($union, 't');
        foreach (self::SORTS[$request->input('sort', 'newest')] as [$column, $direction]) {
            $query->orderBy($column, $direction);
        }

        $page = $query->paginate((int) $request->input('per_page', 50), ['*'], 'page', (int) $request->input('page', 1));

        return response()->json([
            'data' => collect($page->items())->map(fn ($row) => [
                'type'           => $row->type,
                'id'             => (int) $row->id,
                'amount_minor'   => (int) $row->amount_minor,
                // A DATE column on MySQL; text that may carry a time on SQLite.
                'date'           => substr((string) $row->date, 0, 10),
                'note'           => $row->note,
                'payment_method' => $row->payment_method,
                'category_id'    => $row->category_id === null ? null : (int) $row->category_id,
                'category_name'  => $row->category_name,
                'category_icon'  => $row->category_icon,
                'category_color' => $row->category_color,
                'source'         => $row->source,
            ])->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }

    private function expenses(int $uid, Request $request): Builder
    {
        $query = DB::table('app_expenses as e')
            ->join('categories as c', 'c.id', '=', 'e.category_id')
            ->where('e.user_id', $uid)
            ->select([
                DB::raw("'expense' as type"), 'e.id', 'e.amount_minor', 'e.spent_at as date', 'e.note',
                'e.payment_method', 'e.category_id', 'c.name as category_name', 'c.icon as category_icon',
                'c.color as category_color', DB::raw('NULL as source'),
            ]);

        if ($request->filled('category_id')) $query->where('e.category_id', (int) $request->category_id);
        if ($request->filled('search')) $this->search($query, $request->search, ['c.name', 'e.note', 'e.payment_method']);

        return $query;
    }

    private function incomes(int $uid, Request $request): Builder
    {
        $query = DB::table('app_incomes as i')
            ->where('i.user_id', $uid)
            ->select([
                DB::raw("'income' as type"), 'i.id', 'i.amount_minor', 'i.received_at as date', 'i.note',
                'i.payment_method', DB::raw('NULL as category_id'), DB::raw('NULL as category_name'),
                DB::raw('NULL as category_icon'), DB::raw('NULL as category_color'), 'i.source',
            ]);

        if ($request->filled('search')) $this->search($query, $request->search, ['i.source', 'i.note', 'i.payment_method']);

        return $query;
    }

    /** The filters both halves share. */
    private function filtered(Builder $query, Request $request, string $dateColumn, string $alias): Builder
    {
        if ($request->filled('payment_method')) $query->where("{$alias}.payment_method", $request->payment_method);
        if ($request->filled('date_from')) $query->where($dateColumn, '>=', $request->date_from);
        // Both ends are inclusive. "Before the next day" rather than "<= the
        // day", which would drop rows SQLite stores with a time attached.
        if ($request->filled('date_to')) {
            $query->where($dateColumn, '<', Carbon::parse($request->date_to)->addDay()->toDateString());
        }
        if ($request->filled('min_amount_minor')) $query->where("{$alias}.amount_minor", '>=', (int) $request->min_amount_minor);
        if ($request->filled('max_amount_minor')) $query->where("{$alias}.amount_minor", '<=', (int) $request->max_amount_minor);

        return $query;
    }

    /**
     * Case-insensitive substring match on any of the columns. LIKE wildcards
     * in the term are escaped with "!", which both MySQL and SQLite accept as
     * an ESCAPE character (a backslash means different things to each).
     */
    private function search(Builder $query, string $term, array $columns): void
    {
        $needle = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower(trim($term))) . '%';

        $query->where(function (Builder $q) use ($columns, $needle) {
            foreach ($columns as $column) {
                $q->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", [$needle]);
            }
        });
    }
}
