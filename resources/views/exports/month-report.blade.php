{{--
    The monthly report PDF (App\Support\MonthReport, rendered by dompdf).
    dompdf reads CSS 2.1: tables for layout, no flex or grid. DejaVu Sans is
    the bundled font with the ₹ sign; it has no emoji, so categories show
    their colour, not their icon.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Expense report · {{ $monthLabel }}</title>
    <style>
        @page { margin: 30px 34px 46px; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 9.5px; color: #1f2937; line-height: 1.4; }
        .head { background: #002e62; color: #ffffff; padding: 16px 18px; border-radius: 8px; }
        .head td { color: #ffffff; vertical-align: bottom; }
        .head h1 { font-size: 19px; margin: 0; font-weight: bold; }
        .head .sub { font-size: 9.5px; color: #bfe8f2; margin-top: 3px; }
        .head .who { text-align: right; font-size: 9px; color: #dbeafe; }

        .tiles { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .tile { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 12px; }
        .gap { width: 10px; }
        .label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.6px; color: #4b5563; font-weight: bold; }
        .value { font-size: 16px; font-weight: bold; margin-top: 3px; }
        .hint { font-size: 8.5px; color: #4b5563; margin-top: 2px; }

        h2 { font-size: 11.5px; color: #002e62; margin: 20px 0 6px; padding-bottom: 4px; border-bottom: 2px solid #002e62; }
        table.list { width: 100%; border-collapse: collapse; }
        table.list th { text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: 0.4px; color: #4b5563; border-bottom: 1px solid #d1d5db; padding: 5px 6px; }
        table.list td { padding: 5px 6px; border-bottom: 1px solid #eef0f3; vertical-align: middle; }
        table.list tr { page-break-inside: avoid; }
        .num, table.list th.num { text-align: right; white-space: nowrap; }
        .in { color: #15803d; }
        .out { color: #dc2626; }
        .navy { color: #002e62; }
        .muted { color: #6b7280; }
        .dot { display: inline-block; width: 8px; height: 8px; border-radius: 4px; margin-right: 5px; }
        .track { height: 5px; background: #eef0f3; border-radius: 3px; width: 100%; }
        .fill { height: 5px; border-radius: 3px; }
        .pill { padding: 1px 6px; border-radius: 7px; font-size: 8px; font-weight: bold; white-space: nowrap; }
        .pill-ok { background: #dcfce7; color: #15803d; }
        .pill-near { background: #fef3c7; color: #92400e; }
        .pill-over { background: #fee2e2; color: #b91c1c; }
        .empty { padding: 10px 6px; color: #6b7280; }
        .footer { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 8px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 5px; }
        .footer .page:after { content: "Page " counter(page); }
    </style>
</head>
<body>
    <div class="footer">
        <table style="width: 100%"><tr>
            <td>Expense Tracker · {{ $user->name ?: $user->email }} · {{ $monthLabel }}</td>
            <td style="text-align: right"><span class="page"></span></td>
        </tr></table>
    </div>

    <div class="head">
        <table style="width: 100%; border-collapse: collapse"><tr>
            <td>
                <h1>Expense report</h1>
                <div class="sub">{{ $monthLabel }} · amounts in {{ $currency }}</div>
            </td>
            <td class="who">
                {{ $user->name ?: '' }}@if($user->name && $user->email)<br>@endif{{ $user->email }}<br>
                Made {{ $generated }}
            </td>
        </tr></table>
    </div>

    {{-- Totals --}}
    <table class="tiles"><tr>
        <td class="tile">
            <div class="label">Money in</div>
            <div class="value in">{{ $received }}</div>
            <div class="hint">{{ $incomeCount }} {{ \Illuminate\Support\Str::plural('income', $incomeCount) }}</div>
        </td>
        <td class="gap"></td>
        <td class="tile">
            <div class="label">Spent</div>
            <div class="value out">{{ $spent }}</div>
            <div class="hint">{{ $expenseCount }} {{ \Illuminate\Support\Str::plural('expense', $expenseCount) }}</div>
        </td>
        <td class="gap"></td>
        <td class="tile">
            <div class="label">{{ $overspent ? 'Overspent' : 'Saved' }}</div>
            <div class="value {{ $overspent ? 'out' : 'navy' }}">{{ $overspent ? '−' : '' }}{{ $saved }}</div>
            <div class="hint">{{ $overspent ? 'Spent more than came in' : ($savingRate !== null ? $savingRate . '% of money in' : 'Money in minus spent') }}</div>
        </td>
    </tr></table>

    {{-- Where it went --}}
    <h2>Spending by category</h2>
    <table class="list">
        <thead><tr>
            <th>Category</th><th class="num">Entries</th><th style="width: 34%">Share</th><th class="num">%</th><th class="num">Amount</th>
        </tr></thead>
        <tbody>
            @forelse($breakdown as $row)
                <tr>
                    <td><span class="dot" style="background-color: {{ $row['color'] }}"></span>{{ $row['name'] }}</td>
                    <td class="num muted">{{ $row['count'] }}</td>
                    <td><div class="track"><div class="fill" style="width: {{ max(1, $row['share']) }}%; background-color: {{ $row['color'] }}"></div></div></td>
                    <td class="num">{{ rtrim(rtrim(number_format($row['share'], 1), '0'), '.') }}%</td>
                    <td class="num"><strong>{{ $row['amount'] }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">Nothing was spent in {{ $monthLabel }}.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Budgets --}}
    <h2>Budgets</h2>
    <table class="list">
        <thead><tr>
            <th>Budget</th><th class="num">Limit</th><th class="num">Spent</th><th style="width: 22%">Used</th><th class="num">Left</th><th class="num">Status</th>
        </tr></thead>
        <tbody>
            @forelse($budgets as $row)
                <tr>
                    <td><span class="dot" style="background-color: {{ $row['color'] }}"></span>{{ $row['name'] }}</td>
                    <td class="num">{{ $row['budget'] }}</td>
                    <td class="num">{{ $row['spent'] }}</td>
                    <td>
                        <div class="track"><div class="fill" style="width: {{ max(1, round($row['ratio'] * 100)) }}%; background-color: {{ $row['status'] === 'over' ? '#dc2626' : ($row['status'] === 'near' ? '#f59e0b' : '#002e62') }}"></div></div>
                        <div class="muted" style="font-size: 8px; margin-top: 2px">{{ $row['percent'] }}%</div>
                    </td>
                    <td class="num {{ $row['status'] === 'over' ? 'out' : '' }}">{{ $row['remainder'] }}</td>
                    <td class="num">
                        <span class="pill pill-{{ $row['status'] }}">{{ ['ok' => 'On track', 'near' => 'Near limit', 'over' => 'Over budget'][$row['status']] }}</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No budgets were set for {{ $monthLabel }}.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Every entry --}}
    <h2>All entries</h2>
    <table class="list">
        <thead><tr>
            <th style="width: 11%">Date</th><th style="width: 24%">Category or source</th><th>Note</th><th style="width: 15%">Payment</th><th class="num" style="width: 17%">Amount</th>
        </tr></thead>
        <tbody>
            @forelse($entries as $row)
                <tr>
                    <td class="muted">{{ $row['date'] }}</td>
                    <td><span class="dot" style="background-color: {{ $row['color'] ?: '#94A3B8' }}"></span>{{ $row['label'] }}</td>
                    <td>{{ $row['note'] }}</td>
                    <td class="muted">{{ $row['payment'] }}</td>
                    <td class="num {{ $row['income'] ? 'in' : 'out' }}">{{ $row['amount'] }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">Nothing was recorded in {{ $monthLabel }}.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
