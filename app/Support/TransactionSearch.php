<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Expenses and incomes as one list, searched, filtered, sorted and paged in
 * SQL — a UNION ALL of the two tables, so only the page asked for is read.
 * `type` + `id` identify a row: the two tables share ids. Shared by the API
 * and the website; callers validate the filters first.
 */
class TransactionSearch
{
    public const SORTS = [
        'newest'  => [['date', 'desc'], ['id', 'desc'], ['type', 'desc']],
        'oldest'  => [['date', 'asc'], ['id', 'asc'], ['type', 'asc']],
        'highest' => [['amount_minor', 'desc'], ['date', 'desc'], ['id', 'desc'], ['type', 'desc']],
        'lowest'  => [['amount_minor', 'asc'], ['date', 'desc'], ['id', 'desc'], ['type', 'desc']],
    ];

    /**
     * @param array $filters search, type, category_id, payment_method, date_from,
     *                       date_to, min_amount_minor, max_amount_minor, sort
     */
    public static function paginate(int $uid, array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        $filled = fn (string $key) => isset($filters[$key]) && $filters[$key] !== '';

        // A category only applies to expenses, so it narrows to them too.
        $type = $filled('category_id') ? 'expense' : ($filters['type'] ?? null);

        $parts = [];
        if ($type !== 'income') $parts[] = self::filtered(self::expenses($uid, $filters, $filled), $filters, $filled, 'e.spent_at', 'e');
        if ($type !== 'expense') $parts[] = self::filtered(self::incomes($uid, $filters, $filled), $filters, $filled, 'i.received_at', 'i');

        $union = array_shift($parts);
        foreach ($parts as $part) $union->unionAll($part);

        $query = DB::query()->fromSub($union, 't');
        foreach (self::SORTS[$filled('sort') ? $filters['sort'] : 'newest'] as [$column, $direction]) {
            $query->orderBy($column, $direction);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $paginator->setCollection($paginator->getCollection()->map(fn ($row) => [
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
            // A receipt photo on an expense (App\Support\Receipts); always false for an income.
            'has_receipt'    => (bool) $row->has_receipt,
        ])->values());
    }

    /**
     * Every row the filters match, in the same order and shape as a page —
     * for exports (App\Support\TransactionCsv, App\Support\MonthReport).
     */
    public static function all(int $uid, array $filters, int $limit = 50000): \Illuminate\Support\Collection
    {
        return collect(self::paginate($uid, $filters, $limit, 1)->items());
    }

    private static function expenses(int $uid, array $filters, callable $filled): Builder
    {
        $query = DB::table('app_expenses as e')
            ->join('categories as c', 'c.id', '=', 'e.category_id')
            ->where('e.user_id', $uid)
            ->select([
                DB::raw("'expense' as type"), 'e.id', 'e.amount_minor', 'e.spent_at as date', 'e.note',
                'e.payment_method', 'e.category_id', 'c.name as category_name', 'c.icon as category_icon',
                'c.color as category_color', DB::raw('NULL as source'),
                DB::raw('CASE WHEN e.receipt_path IS NULL THEN 0 ELSE 1 END as has_receipt'),
            ]);

        if ($filled('category_id')) $query->where('e.category_id', (int) $filters['category_id']);
        if ($filled('search')) self::search($query, $filters['search'], ['c.name', 'e.note', 'e.payment_method']);

        return $query;
    }

    private static function incomes(int $uid, array $filters, callable $filled): Builder
    {
        $query = DB::table('app_incomes as i')
            ->where('i.user_id', $uid)
            ->select([
                DB::raw("'income' as type"), 'i.id', 'i.amount_minor', 'i.received_at as date', 'i.note',
                'i.payment_method', DB::raw('NULL as category_id'), DB::raw('NULL as category_name'),
                DB::raw('NULL as category_icon'), DB::raw('NULL as category_color'), 'i.source',
                DB::raw('0 as has_receipt'),
            ]);

        if ($filled('search')) self::search($query, $filters['search'], ['i.source', 'i.note', 'i.payment_method']);

        return $query;
    }

    /** The filters both halves share. */
    private static function filtered(Builder $query, array $filters, callable $filled, string $dateColumn, string $alias): Builder
    {
        if ($filled('payment_method')) $query->where("{$alias}.payment_method", $filters['payment_method']);
        if ($filled('date_from')) $query->where($dateColumn, '>=', $filters['date_from']);
        // Both ends are inclusive. "Before the next day" rather than "<= the
        // day", which would drop rows SQLite stores with a time attached.
        if ($filled('date_to')) {
            $query->where($dateColumn, '<', Carbon::parse($filters['date_to'])->addDay()->toDateString());
        }
        if ($filled('min_amount_minor')) $query->where("{$alias}.amount_minor", '>=', (int) $filters['min_amount_minor']);
        if ($filled('max_amount_minor')) $query->where("{$alias}.amount_minor", '<=', (int) $filters['max_amount_minor']);

        return $query;
    }

    /**
     * Case-insensitive substring match on any of the columns. LIKE wildcards
     * in the term are escaped with "!", which both MySQL and SQLite accept as
     * an ESCAPE character (a backslash means different things to each).
     */
    private static function search(Builder $query, string $term, array $columns): void
    {
        $needle = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower(trim($term))) . '%';

        $query->where(function (Builder $q) use ($columns, $needle) {
            foreach ($columns as $column) {
                $q->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", [$needle]);
            }
        });
    }
}
