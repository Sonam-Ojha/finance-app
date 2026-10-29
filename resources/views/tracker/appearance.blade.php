<x-app-layout>
    <x-slot name="title">Appearance</x-slot>
    <x-slot name="subtitle">Choose how the app looks.</x-slot>

    <div class="grid items-start gap-4 lg:grid-cols-2">
        <section class="card space-y-4">
            <div>
                <h2 class="card-title">Theme</h2>
                <p class="mt-1 text-[13px] muted">System follows your device's light or dark setting.</p>
            </div>

            <form method="POST" action="{{ route('settings.theme') }}" class="seg w-full">
                @csrf @method('PUT')
                @foreach(\App\Http\Controllers\Tracker\SettingsController::THEMES as $key => $label)
                    <button type="submit" name="theme_mode" value="{{ $key }}" aria-pressed="{{ $theme === $key ? 'true' : 'false' }}"
                            class="seg-item flex-1 {{ $theme === $key ? 'seg-on' : '' }}">{{ $label }}</button>
                @endforeach
            </form>

            <p class="text-sm muted" x-data>
                @if($theme === 'system')
                    Following your device, currently <span x-text="document.documentElement.classList.contains('dark') ? 'dark' : 'light'"></span>.
                @else
                    Always {{ $theme }}, whatever your device uses.
                @endif
            </p>
        </section>

        <section class="card space-y-4">
            <div>
                <h2 class="card-title">Sidebar colour</h2>
                <p class="mt-1 text-[13px] muted">One tap saves it.{{ auth()->user()->isAdmin() ? ' The admin panel uses it too.' : '' }}</p>
            </div>
            @include('partials.sidebar-colors')
        </section>
    </div>
</x-app-layout>
