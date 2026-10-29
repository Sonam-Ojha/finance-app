<x-app-layout>
    <x-slot name="title">Notifications</x-slot>
    @php
        $tones = [
            'danger'  => ['warning', 'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-300'],
            'warning' => ['warning', 'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300'],
            'info'    => ['info', 'bg-brand-tint text-brand-navy dark:bg-brand-cyan/15 dark:text-brand-cyan'],
        ];
    @endphp

    <div class="max-w-2xl space-y-4">
        @if($messages->isNotEmpty())
            <h3 class="section-label">Messages</h3>
            @foreach($messages as $message)
                <div class="card flex gap-3">
                    <span class="w-10 h-10 rounded-full bg-brand-tint text-brand-navy dark:bg-brand-cyan/15 dark:text-brand-cyan flex items-center justify-center flex-shrink-0">
                        <x-icon name="megaphone" />
                    </span>
                    <div class="min-w-0">
                        <p class="font-semibold strong">{{ $message['title'] }}</p>
                        <p class="text-sm mt-0.5 whitespace-pre-line break-words">{{ $message['body'] }}</p>
                        <p class="text-xs muted mt-1.5">{{ $message['sent'] }}</p>
                    </div>
                </div>
            @endforeach
            <h3 class="section-label pt-2">Alerts</h3>
        @endif

        <p class="text-sm muted">Worked out from your own numbers for {{ $monthLabel }} — budgets you set, spending you recorded.</p>

        @forelse($alerts as $alert)
            @php [$icon, $tone] = $tones[$alert['tone']]; @endphp
            <a href="{{ $alert['href'] }}" class="card flex gap-3 transition-shadow hover:ring-2 hover:ring-brand-navy/15 dark:hover:ring-brand-cyan/30">
                <span class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 {{ $tone }}"><x-icon :name="$icon" /></span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold strong">{{ $alert['title'] }}</p>
                    <p class="text-sm mt-0.5">{{ $alert['body'] }}</p>
                </div>
                <x-icon name="chevron-right" class="w-4 h-4 muted self-center" />
            </a>
        @empty
            <div class="card text-center py-10">
                <p class="font-semibold strong">Nothing to flag</p>
                <p class="text-sm muted mt-1">Every budget is on track and there is nothing else worth interrupting you about.</p>
            </div>
        @endforelse
    </div>
</x-app-layout>
