<x-app-layout>
    <x-slot name="title">Notifications</x-slot>
    @php
        $tones = [
            'danger'  => ['warning', 'bg-red-50 text-status-error dark:bg-red-500/15 dark:text-red-300'],
            'warning' => ['warning', 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'],
            'info'    => ['info', ''],
        ];
    @endphp

    <div class="space-y-6">
        @if($messages->isNotEmpty())
            <section class="space-y-3">
                <h2 class="section-label">Messages</h2>
                {{-- Two to a row on wide screens --}}
                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach($messages as $message)
                        <div class="card flex gap-3">
                            <span class="icon-tile"><x-icon name="megaphone" /></span>
                            <div class="min-w-0">
                                <p class="font-semibold strong">{{ $message['title'] }}</p>
                                <p class="mt-0.5 whitespace-pre-line break-words">{{ $message['body'] }}</p>
                                <p class="mt-1.5 text-[13px] muted">{{ $message['sent'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="space-y-3">
            <h2 class="section-label">Alerts</h2>
            <p class="text-sm muted">Worked out from your own numbers for {{ $monthLabel }} — budgets you set, spending you recorded.</p>

            @if($alerts)
                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach($alerts as $alert)
                        @php [$icon, $tone] = $tones[$alert['tone']]; @endphp
                        <a href="{{ $alert['href'] }}" class="card flex gap-3 transition-shadow hover:ring-2 hover:ring-brand-navy/15 dark:hover:ring-brand-cyan/30">
                            <span class="icon-tile {{ $tone }}"><x-icon :name="$icon" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold strong">{{ $alert['title'] }}</p>
                                <p class="mt-0.5">{{ $alert['body'] }}</p>
                            </div>
                            <x-icon name="chevron-right" class="w-4 h-4 muted self-center" />
                        </a>
                    @endforeach
                </div>
            @else
                <div class="card py-10 text-center">
                    <p class="font-semibold strong">Nothing to flag</p>
                    <p class="mt-1 text-sm muted">Every budget is on track and there is nothing else worth interrupting you about.</p>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
