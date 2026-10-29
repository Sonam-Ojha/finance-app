@props(['users', 'max' => 5])
{{-- A group's people as overlapping photos or initials. Not clickable, so it
     can sit inside a link (x-admin.avatar opens the photo instead). --}}
@php
    $users = collect($users)->filter()->values();
    $shown = $users->take($max);
    $more  = $users->count() - $shown->count();
@endphp
<span class="flex items-center -space-x-1" aria-hidden="true">
    @foreach($shown as $user)
        @php $url = \App\Support\Accounts::avatarUrl($user); @endphp
        @if($url)
            <img src="{{ $url }}" alt="" loading="lazy" class="h-8 w-8 flex-shrink-0 rounded-full object-cover ring-2 ring-surface-card dark:ring-night-card">
        @else
            <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-brand-tint text-[11px] font-semibold text-brand-navy ring-2 ring-surface-card dark:bg-night-line dark:text-brand-cyan dark:ring-night-card">{{ \App\Support\Accounts::initials($user) }}</span>
        @endif
    @endforeach
    @if($more > 0)
        <span class="flex h-8 min-w-8 flex-shrink-0 items-center justify-center rounded-full bg-surface-page px-1.5 text-[11px] font-semibold text-ink-muted ring-2 ring-surface-card dark:bg-night-line dark:text-night-muted dark:ring-night-card">+{{ $more }}</span>
    @endif
</span>
