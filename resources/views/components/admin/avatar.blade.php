{{-- A user's photo in the admin tables; a click opens it large (the photo
     viewer in layouts/shell). Without a photo: their initials. --}}
@props(['user', 'size' => 'h-10 w-10'])
@php
    $url = \App\Support\Accounts::avatarUrl($user);
    $name = $user->name ?: 'User #' . $user->id;
@endphp
@if($url)
    <button type="button" aria-label="Open {{ $name }}'s photo"
            @click="$dispatch('open-photo', { src: @js($url), name: @js($name) })"
            class="{{ $size }} flex-shrink-0 overflow-hidden rounded-full ring-2 ring-transparent transition hover:ring-brand-cyan">
        <img src="{{ $url }}" alt="" loading="lazy" class="h-full w-full object-cover">
    </button>
@else
    <span class="{{ $size }} flex flex-shrink-0 items-center justify-center rounded-full bg-brand-tint text-[13px] font-semibold text-brand-navy" aria-hidden="true">{{ \App\Support\Accounts::initials($user) }}</span>
@endif
