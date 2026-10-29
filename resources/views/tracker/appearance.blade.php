<x-app-layout>
    <x-slot name="title">Appearance</x-slot>

    <div class="max-w-2xl mx-auto space-y-4">
        <div class="card space-y-4">
            <p class="text-sm muted">Choose how the app looks. System follows your device's light or dark setting.</p>

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
        </div>
    </div>
</x-app-layout>
