{{-- The monthly summary email (App\Notifications\MonthlySummaryMail). Markdown tables must not be indented. --}}
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('app.url')">
Expense Tracker
</x-mail::header>
</x-slot:header>

**{{ $greeting }}**

Here is how {{ $s['label'] }} went, across {{ $s['entries'] }} {{ \Illuminate\Support\Str::plural('entry', $s['entries']) }}.

<x-mail::table>
| | |
|:--|--:|
| Money in | **{{ $s['in'] }}** |
| Spent | **{{ $s['spent'] }}** |
| {{ $s['overspent'] ? 'Spent more than came in' : 'Saved' }} | **{{ $s['saved'] }}** |
</x-mail::table>

@if($s['change'])
{{ $s['change'] }}

@endif
@if($s['categories'])
**Where it went**

<x-mail::table>
| Category | Spent | Share |
|:--|--:|--:|
@foreach($s['categories'] as $row)
| {{ $row['name'] }} | {{ $row['amount'] }} | {{ $row['share'] }}% |
@endforeach
</x-mail::table>

@endif
**Budgets**

@forelse($s['over'] as $line)
- {{ $line }}
@empty
Every budget held. Nicely done.
@endforelse

<x-mail::button :url="url('/analytics')">
See the whole month
</x-mail::button>

<x-slot:subcopy>
<x-mail::subcopy>
You get this summary on the 1st of every month. Turn it off in Settings → Reminders.
</x-mail::subcopy>
</x-slot:subcopy>

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} Expense Tracker
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
