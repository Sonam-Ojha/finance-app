{{-- A phone, for previews (admin/notifications): the frame, the status bar
     and the home bar around whatever screen is in the slot. `light` is for
     a white app screen, which needs dark status-bar text. --}}
@props(['time', 'light' => false])
<div class="rounded-[48px] bg-[#0b0f19] p-[10px] shadow-[0_24px_48px_-16px_rgba(0,46,98,.45)]">
    <div {{ $attributes->merge(['class' => 'relative flex h-[540px] w-[258px] flex-col overflow-hidden rounded-[38px] ' . ($light ? 'bg-white text-ink' : 'text-white')]) }}>
        <div class="flex flex-shrink-0 items-center justify-between px-7 pb-1 pt-3 text-[12px] font-semibold" aria-hidden="true">
            <span>{{ $time }}</span>
            <span class="absolute left-1/2 top-[10px] h-[26px] w-[88px] -translate-x-1/2 rounded-full bg-black"></span>
            <span class="flex items-center gap-1">
                <svg class="h-3 w-4" viewBox="0 0 16 12" fill="currentColor"><rect x="0" y="8" width="3" height="4" rx="1"/><rect x="4.3" y="5.5" width="3" height="6.5" rx="1"/><rect x="8.6" y="3" width="3" height="9" rx="1"/><rect x="12.9" y="0" width="3" height="12" rx="1"/></svg>
                <svg class="h-3 w-4" viewBox="0 0 16 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M1.5 4.5a9.5 9.5 0 0 1 13 0M4 7.2a6 6 0 0 1 8 0"/><circle cx="8" cy="10" r="1.2" fill="currentColor" stroke="none"/></svg>
                <svg class="h-3 w-6" viewBox="0 0 26 12" fill="none"><rect x=".75" y=".75" width="21.5" height="10.5" rx="3" stroke="currentColor" stroke-opacity=".5" stroke-width="1.5"/><rect x="2.5" y="2.5" width="18" height="7" rx="1.5" fill="currentColor"/><rect x="23.5" y="4" width="2" height="4" rx="1" fill="currentColor" fill-opacity=".5"/></svg>
            </span>
        </div>

        {{ $slot }}

        <span class="mx-auto mb-2 mt-1 h-[5px] w-[110px] flex-shrink-0 rounded-full {{ $light ? 'bg-ink' : 'bg-white/85' }}" aria-hidden="true"></span>
    </div>
</div>
