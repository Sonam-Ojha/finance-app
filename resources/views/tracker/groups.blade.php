<x-app-layout>
    <x-slot name="title">Groups</x-slot>
    <x-slot name="subtitle">Share expenses with friends, family or flatmates and see who owes whom.</x-slot>
    <x-slot name="actions">
        <a href="{{ route('groups.create') }}" class="btn-primary"><x-icon name="plus" class="h-5 w-5" /> New group</a>
    </x-slot>

    @if($groups->isEmpty())
        <div class="card py-12 text-center">
            <span class="icon-tile mx-auto !h-14 !w-14"><x-icon name="users" class="h-7 w-7" /></span>
            <p class="mt-4 font-semibold strong">No groups yet</p>
            <p class="mx-auto mt-1 max-w-md text-sm muted">Make one for a trip, your flat or a dinner out. Add people by their email, record what anyone pays, and the group keeps track of who owes whom.</p>
            <a href="{{ route('groups.create') }}" class="btn-primary mt-5">Create a group</a>
        </div>
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach($groups as $group)
                @php
                    $mine  = $balances[$group->id][auth()->id()] ?? 0;
                    $count = $group->members->count();
                @endphp
                <a href="{{ route('groups.show', $group) }}"
                   class="card flex items-center gap-4 no-underline ring-1 ring-transparent transition hover:ring-brand-navy/20 dark:hover:ring-brand-cyan/30">
                    <span class="icon-tile !h-12 !w-12 text-2xl leading-none">{{ $group->emoji ?: '👥' }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-semibold strong">{{ $group->name }}</span>
                        <span class="mt-1.5 flex items-center gap-2">
                            <x-tracker.member-stack :users="$group->members->pluck('user')" />
                            <span class="text-xs muted">{{ $count }} {{ Str::plural('person', $count) }}</span>
                        </span>
                    </span>
                    <span class="flex-shrink-0 text-right">
                        @if($mine > 0)
                            <span class="block text-xs muted">You are owed</span>
                            <span class="block font-bold money-in">{{ $money->format($mine) }}</span>
                        @elseif($mine < 0)
                            <span class="block text-xs muted">You owe</span>
                            <span class="block font-bold money-out">{{ $money->format(-$mine) }}</span>
                        @else
                            <span class="block text-sm font-semibold muted">Settled up</span>
                        @endif
                    </span>
                    <x-icon name="chevron-right" class="h-5 w-5 flex-shrink-0 text-ink-muted dark:text-night-muted" />
                </a>
            @endforeach
        </div>
    @endif
</x-app-layout>
