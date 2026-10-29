<x-admin-layout>
    <x-slot name="title">Support</x-slot>
    @php
        $waiting = (int) ($counts['open'] ?? 0);
        $all = (int) $counts->sum();
        $badge = [
            'open'     => ['adm-badge-medium', 'Waiting for you'],
            'answered' => ['adm-badge-low', 'Answered'],
            'closed'   => ['adm-badge bg-surface-page text-ink-muted', 'Closed'],
        ];
    @endphp
    <x-slot name="subtitle">
        @if($waiting)
            {{ \App\Support\Counts::indian($waiting) }} {{ \Illuminate\Support\Str::plural('request', $waiting) }} waiting for a reply, the longest-waiting first.
        @else
            Nothing waiting for a reply.
        @endif
        People write from Help & support on the website or in the app.
    </x-slot>
    <x-slot name="actions">
        <form method="GET" action="{{ route('admin.support.index') }}" role="search" class="flex flex-wrap items-center gap-2">
            @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
            <label for="support-search" class="sr-only">Search by subject, name or email</label>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-ink-muted" />
                <input id="support-search" type="search" name="q" value="{{ $search }}" placeholder="Search subject, name or email" class="adm-input w-full pl-10 sm:w-[300px]">
            </div>
            <button type="submit" class="adm-btn-primary !h-11">Search</button>
        </form>
    </x-slot>

    <nav class="adm-seg self-start overflow-x-auto" aria-label="Filter">
        <a href="{{ route('admin.support.index', array_filter(['q' => $search])) }}" class="adm-seg-item gap-1.5" @unless($status) aria-current="page" @endunless>All <span class="text-xs opacity-80">{{ $all }}</span></a>
        @foreach(\App\Models\SupportTicket::STATUSES as $key => $label)
            <a href="{{ route('admin.support.index', array_filter(['q' => $search, 'status' => $key])) }}" class="adm-seg-item gap-1.5" @if($status === $key) aria-current="page" @endif>
                {{ $label }} <span class="text-xs opacity-80">{{ (int) ($counts[$key] ?? 0) }}</span>
            </a>
        @endforeach
    </nav>

    <section class="adm-card min-w-0">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[680px] text-sm">
                <thead>
                    <tr>
                        <th scope="col" class="adm-th">Request</th>
                        <th scope="col" class="adm-th">From</th>
                        <th scope="col" class="adm-th">Status</th>
                        <th scope="col" class="adm-th">Messages</th>
                        <th scope="col" class="adm-th">Last activity</th>
                        <th scope="col" class="adm-th"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets as $ticket)
                        <tr>
                            <td class="adm-td max-w-[320px]">
                                <a href="{{ route('admin.support.show', $ticket) }}" class="block truncate font-semibold text-brand-navy hover:underline">{{ $ticket->subject }}</a>
                                <p class="adm-caption font-mono">#{{ $ticket->id }}</p>
                            </td>
                            <td class="adm-td">
                                @if($ticket->user)
                                    <div class="flex items-center gap-3">
                                        <x-admin.avatar :user="$ticket->user" size="h-9 w-9" />
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold">{{ $ticket->user->name ?: '—' }}</p>
                                            <p class="adm-caption truncate">{{ $ticket->user->email }}</p>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-ink-muted">Deleted account</span>
                                @endif
                            </td>
                            <td class="adm-td"><span class="{{ $badge[$ticket->status][0] }}">{{ $badge[$ticket->status][1] }}</span></td>
                            <td class="adm-td">{{ $ticket->messages_count }}</td>
                            <td class="adm-td whitespace-nowrap text-ink-muted">{{ \App\Http\Controllers\Admin\DashboardController::when($ticket->updated_at, true) }}</td>
                            <td class="adm-td text-right">
                                <a href="{{ route('admin.support.show', $ticket) }}" class="adm-link">{{ $ticket->status === 'open' ? 'Reply' : 'View' }}<span class="sr-only"> {{ $ticket->subject }}</span></a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="adm-td py-10 text-center text-ink-muted">
                                @if($search !== '')
                                    No request matches “{{ $search }}”.
                                @elseif($status)
                                    No {{ strtolower(\App\Models\SupportTicket::STATUSES[$status]) }} requests.
                                @else
                                    No support requests yet.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($tickets->hasPages())
            <div class="mt-4">{{ $tickets->links() }}</div>
        @endif
    </section>
</x-admin-layout>
