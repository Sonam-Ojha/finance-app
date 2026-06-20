<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; margin: 0; padding: 20px; }
    h1 { font-size: 18px; color: #1e40af; margin-bottom: 4px; }
    .subtitle { color: #6b7280; font-size: 11px; margin-bottom: 16px; }
    .summary-box { display: inline-block; background: #eff6ff; border-left: 4px solid #3b82f6; padding: 8px 16px; margin-bottom: 16px; border-radius: 4px; }
    .summary-box p { margin: 2px 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th { background: #1e3a8a; color: #fff; text-align: left; padding: 7px 10px; font-size: 10px; }
    td { padding: 6px 10px; border-bottom: 1px solid #e5e7eb; }
    tr:nth-child(even) td { background: #f8fafc; }
    .amount { text-align: right; font-weight: bold; }
    .footer { margin-top: 20px; font-size: 9px; color: #9ca3af; text-align: center; }
    .profit { color: #059669; }
    .loss { color: #dc2626; }
</style>
</head>
<body>
    <h1>{{ ucfirst(str_replace('_', ' ', $type)) }} Report</h1>
    <p class="subtitle">
        Period: {{ $month ? date('F', mktime(0,0,0,$month,1)).' ' : 'Full Year ' }}{{ $year }}
        &nbsp;|&nbsp; Generated: {{ now()->format('d M Y, h:i A') }}
    </p>

    @if($type === 'profit_loss')
        <div class="summary-box">
            <p><strong>Total Income:</strong> ₹{{ number_format($totalIncome, 2) }}</p>
            <p><strong>Total Expense:</strong> ₹{{ number_format($totalExpense, 2) }}</p>
            <p class="{{ $net >= 0 ? 'profit' : 'loss' }}"><strong>Net {{ $net >= 0 ? 'Profit' : 'Loss' }}:</strong> ₹{{ number_format(abs($net), 2) }}</p>
        </div>
    @elseif($type === 'investment')
        <div class="summary-box">
            <p><strong>Total Invested:</strong> ₹{{ number_format($total, 2) }}</p>
            <p><strong>Current Value:</strong> ₹{{ number_format($currentTotal, 2) }}</p>
            <p class="{{ $currentTotal >= $total ? 'profit' : 'loss' }}"><strong>Gain/Loss:</strong> ₹{{ number_format(abs($currentTotal - $total), 2) }}</p>
        </div>
    @elseif($type === 'loan')
        <div class="summary-box">
            <p><strong>Total Pending:</strong> ₹{{ number_format($totalPending, 2) }}</p>
        </div>
    @else
        <div class="summary-box">
            <p><strong>Total:</strong> ₹{{ number_format($total ?? 0, 2) }}</p>
        </div>
    @endif

    @if(isset($records) && $records->count())
    <table>
        <thead>
            <tr>
                @if($type === 'income')
                    <th>Date</th><th>Type</th><th>Details</th><th>Mode</th><th style="text-align:right">Amount (₹)</th>
                @elseif($type === 'expense')
                    <th>Date</th><th>Category</th><th>Description</th><th style="text-align:right">Amount (₹)</th>
                @elseif($type === 'investment')
                    <th>Name</th><th>Category</th><th style="text-align:right">Invested</th><th style="text-align:right">Current Value</th>
                @elseif($type === 'loan')
                    <th>Loan Name</th><th>Lender</th><th>Status</th><th style="text-align:right">Total</th><th style="text-align:right">Pending</th>
                @elseif($type === 'commission')
                    <th>Date</th><th>Source</th><th>Client</th><th>Status</th><th style="text-align:right">Amount (₹)</th>
                @elseif($type === 'profit_loss')
                    <th>Date</th><th>Category</th><th>Description</th><th style="text-align:right">Income</th><th style="text-align:right">Expense</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($records as $r)
            <tr>
                @if($type === 'income')
                    <td>{{ $r->date->format('d/m/Y') }}</td>
                    <td>{{ \App\Models\Income::typeLabel($r->type) }}</td>
                    <td>{{ $r->company_name ?? $r->person_name ?? $r->business_name ?? '—' }}</td>
                    <td>{{ ucfirst(str_replace('_',' ',$r->payment_mode ?? '')) }}</td>
                    <td class="amount">{{ number_format($r->amount, 2) }}</td>
                @elseif($type === 'expense')
                    <td>{{ $r->date->format('d/m/Y') }}</td>
                    <td>{{ $r->category->name ?? '—' }}</td>
                    <td>{{ $r->description }}</td>
                    <td class="amount">{{ number_format($r->amount, 2) }}</td>
                @elseif($type === 'investment')
                    <td>{{ $r->investment_name }}</td>
                    <td>{{ strtoupper($r->category) }}</td>
                    <td class="amount">{{ number_format($r->amount_invested, 2) }}</td>
                    <td class="amount {{ $r->current_value >= $r->amount_invested ? 'profit' : 'loss' }}">{{ number_format($r->current_value ?? 0, 2) }}</td>
                @elseif($type === 'loan')
                    <td>{{ $r->loan_name }}</td>
                    <td>{{ $r->bank_or_person_name }}</td>
                    <td>{{ ucfirst($r->status) }}</td>
                    <td class="amount">{{ number_format($r->total_amount, 2) }}</td>
                    <td class="amount loss">{{ number_format($r->pending_amount, 2) }}</td>
                @elseif($type === 'commission')
                    <td>{{ $r->date->format('d/m/Y') }}</td>
                    <td>{{ $r->source_name }}</td>
                    <td>{{ $r->client_name }}</td>
                    <td>{{ ucfirst($r->status) }}</td>
                    <td class="amount">{{ number_format($r->commission_amount, 2) }}</td>
                @elseif($type === 'profit_loss')
                    <td>{{ $r->date->format('d/m/Y') }}</td>
                    <td>{{ isset($r->category) ? ($r->category->name ?? '—') : \App\Models\Income::typeLabel($r->type ?? '') }}</td>
                    <td>{{ $r->description ?? ($r->note ?? '—') }}</td>
                    <td class="amount profit">{{ isset($r->amount) && !isset($r->expense_category_id) ? number_format($r->amount, 2) : '—' }}</td>
                    <td class="amount loss">{{ isset($r->expense_category_id) ? number_format($r->amount, 2) : '—' }}</td>
                @endif
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
        <p style="color:#9ca3af;margin-top:12px;">No records found for the selected period.</p>
    @endif

    <div class="footer">Personal Money Control App — Confidential Report</div>
</body>
</html>
