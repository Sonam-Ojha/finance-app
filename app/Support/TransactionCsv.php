<?php

namespace App\Support;

use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * Expenses and incomes as a CSV file, shared by the website's Export and
 * GET /api/v1/export/transactions.csv. Rows come from App\Support\TransactionSearch,
 * so the file holds exactly what the list with the same filters shows.
 *
 * Amounts are rupees with two decimals (the stored value, whatever currency
 * the user reads in), as plain numbers a spreadsheet can add up.
 */
class TransactionCsv
{
    public const HEADER = ['Date', 'Type', 'Category or Source', 'Note', 'Payment method', 'Amount (INR)'];

    /** @param array $filters as TransactionSearch takes them */
    public static function download(int $uid, array $filters, string $filename): Response
    {
        return response(self::build(TransactionSearch::all($uid, $filters)), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }

    /** The file's text: a byte-order mark (so Excel reads ₹ and emoji right), the header, one line per row. */
    public static function build(Collection $rows): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, self::HEADER);
        foreach ($rows as $row) {
            $income = $row['type'] === 'income';
            fputcsv($out, [
                $row['date'],
                $income ? 'Income' : 'Expense',
                self::text($income ? $row['source'] : $row['category_name']),
                self::text($row['note']),
                PaymentMethods::label($row['payment_method']) ?? '',
                number_format($row['amount_minor'] / 100, 2, '.', ''),
            ]);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /** "transactions-2026-10-01-to-2026-10-31.csv"; an open end reads "start" or "today". */
    public static function filename(?string $from, ?string $to): string
    {
        return 'transactions-' . ($from ?? 'start') . '-to-' . ($to ?? Labels::now()->toDateString()) . '.csv';
    }

    /**
     * Typed text, made safe for spreadsheets: a cell starting with = + - @
     * would run as a formula when opened, so it gets a leading apostrophe.
     */
    private static function text(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $value : $value;
    }
}
