@props(['row', 'money', 'showDay' => true, 'deletable' => false])
{{-- One expense or income, shaped as App\Support\TransactionSearch returns it. --}}
@php
    $income = $row['type'] === 'income';
    $title  = trim((string) $row['note']) ?: ($income ? $row['source'] : $row['category_name']);
    $href   = $income ? route('incomes.edit', $row['id']) : route('expenses.edit', $row['id']);
    $meta   = array_filter([
        $showDay ? \App\Support\Labels::dayHeading($row['date']) : null,
        \App\Support\PaymentMethods::label($row['payment_method']),
        // A receipt photo is attached; the expense's page shows it (App\Support\Receipts).
        ! $income && ($row['has_receipt'] ?? false) ? '🧾 Receipt' : null,
    ]);
    $amount = $money->format($row['amount_minor']);
@endphp
<div class="flex items-center gap-2 py-2.5">
    <a href="{{ $href }}" class="flex items-center gap-3 flex-1 min-w-0 rounded-xl -mx-2 px-2 py-1 hover:bg-surface-page dark:hover:bg-white/5">
        <x-tracker.badge :icon="$income ? \App\Support\Labels::sourceIcon($row['source']) : $row['category_icon']"
                         :color="$income ? '#10B981' : $row['category_color']" />
        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium strong truncate">{{ $title }}</p>
            <p class="text-xs muted truncate">
                <span class="font-semibold {{ $income ? 'money-in' : 'money-out' }}">{{ $income ? '+' : '−' }}{{ $amount }}</span>@foreach($meta as $part) · {{ $part }}@endforeach
            </p>
        </div>
        <span class="hidden sm:inline-block max-w-[9rem] truncate rounded-full px-2.5 py-0.5 text-xs font-medium"
              style="background-color: {{ $income ? '#10B981' : $row['category_color'] }}24; color: {{ $income ? '#059669' : $row['category_color'] }}">
            {{ $income ? 'Income' : $row['category_name'] }}
        </span>
    </a>
    @if($deletable)
        <form method="POST" action="{{ $income ? route('incomes.destroy', $row['id']) : route('expenses.destroy', $row['id']) }}"
              data-confirm-title="{{ $income ? 'Delete income?' : 'Delete expense?' }}"
              data-confirm-message="Remove {{ $amount }} from {{ $income ? $row['source'] : $row['category_name'] }}?"
              data-confirm-button="Delete">
            @csrf @method('DELETE')
            <input type="hidden" name="back" value="{{ request()->fullUrl() }}">
            <button type="submit" class="p-2 rounded-lg text-ink-muted dark:text-night-muted hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10" aria-label="Delete {{ $income ? 'income' : 'expense' }}">
                <x-icon name="trash" class="w-4 h-4" />
            </button>
        </form>
    @endif
</div>
