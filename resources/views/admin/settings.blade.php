<x-admin-layout>
    <x-slot name="title">Settings</x-slot>
    <x-slot name="subtitle">How the admin panel looks to you.</x-slot>

    <section class="adm-card max-w-2xl">
        <h2 class="adm-h2">Sidebar colour</h2>
        <p class="adm-caption mt-1">One tap saves it. The sidebar takes this colour here and in My account.</p>
        <div class="mt-4">@include('partials.sidebar-colors')</div>
    </section>

    <section class="adm-card max-w-2xl">
        <h2 class="adm-h2">Your account</h2>
        <p class="adm-caption mt-1">These live in My account, with your own expenses.</p>
        <div class="mt-3 divide-soft">
            @foreach([
                [route('profile.edit'), 'Account information', 'user'],
                [route('account.password'), auth()->user()->has_password ? 'Change password' : 'Set a password', 'lock'],
                [route('settings.appearance'), 'Light or dark theme', 'sun'],
            ] as [$href, $label, $icon])
                <a href="{{ $href }}" class="row-link !px-0">
                    <span class="icon-tile"><x-icon :name="$icon" /></span>
                    <span class="flex-1 font-semibold">{{ $label }}</span>
                    <x-icon name="chevron-right" class="h-4 w-4 text-ink-muted" />
                </a>
            @endforeach
        </div>
    </section>
</x-admin-layout>
