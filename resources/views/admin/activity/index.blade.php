<x-admin-layout>
    <x-slot name="title">Activity log</x-slot>
    @php
        $withIds = fn (string $text) => preg_replace('/#(\d+)/', '<span class="font-mono text-[13px]">#$1</span>', e($text));
    @endphp

    <x-slot name="subtitle">Every change an admin made, newest first.</x-slot>

    <section class="adm-card min-w-0">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[600px] text-sm">
                <thead>
                    <tr>
                        <th scope="col" class="adm-th">When</th>
                        <th scope="col" class="adm-th">Admin</th>
                        <th scope="col" class="adm-th">What happened</th>
                        <th scope="col" class="adm-th">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="adm-td whitespace-nowrap text-ink-muted">{{ $log->created_at?->timezone(config('app.display_timezone'))->format('j M Y, g:i A') }}</td>
                            <td class="adm-td">
                                {{ $log->admin_id === auth()->id() ? 'You' : ($log->admin?->name ?: 'A former admin') }}
                                @if($log->admin)<p class="adm-caption">{{ mask_email($log->admin->email) }}</p>@endif
                            </td>
                            <td class="adm-td">{!! $withIds($log->description) !!}</td>
                            <td class="adm-td"><span class="font-mono text-[13px]">{{ $log->action }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="adm-td py-10 text-center text-ink-muted">Nothing yet. Role changes and sent notifications show up here.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="mt-4">{{ $logs->links() }}</div>
        @endif
    </section>
</x-admin-layout>
