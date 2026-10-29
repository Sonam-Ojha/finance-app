{{-- The sidebar colour picker (App\Support\SidebarColors): one tap saves it.
     Settings → Appearance and Admin → Settings both show it; the choice
     colours the sidebar in both views. --}}
@php $current = \App\Support\SidebarColors::key(auth()->user()); @endphp
<form method="POST" action="{{ route('settings.sidebar') }}" class="grid grid-cols-[repeat(auto-fill,minmax(88px,1fr))] gap-2">
    @csrf @method('PUT')
    @foreach(\App\Support\SidebarColors::ALL as $key => [$label, $color])
        @php $on = $current === $key; @endphp
        <button type="submit" name="sidebar_color" value="{{ $key }}" aria-pressed="{{ $on ? 'true' : 'false' }}"
                class="flex flex-col items-center gap-2 rounded-card border-2 p-2 text-[13px] {{ $on ? 'border-brand-navy font-semibold strong dark:border-brand-cyan' : 'border-transparent muted hover:border-surface-field dark:hover:border-night-field' }}">
            <span class="flex h-12 w-full items-center justify-center rounded-chip text-white" style="background-color: {{ $color }}">
                @if($on)<x-icon name="check" class="h-5 w-5" />@endif
            </span>
            {{ $label }}
        </button>
    @endforeach
</form>
