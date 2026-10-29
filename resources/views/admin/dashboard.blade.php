<x-admin-layout>
    <x-slot name="title">Dashboard</x-slot>
    @php
        $bars = $signups['bars'];
        $first = $bars[0];
        $last = $bars[count($bars) - 1];
        $fills = ['Android' => 'bg-brand-navy', 'iPhone' => 'bg-brand-cyan', 'Web app' => 'bg-chart-3'];
        // An action log line with its ids ("user #4821") set in the mono face.
        $withIds = fn (string $text) => preg_replace('/#(\d+)/', '<span class="font-mono text-[13px]">#$1</span>', e($text));
    @endphp

    {{-- 4.1 Header --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="adm-h1">Dashboard</h1>
            <p class="mt-1 text-sm text-ink-muted">Every account at a glance · Updated {{ $updatedAt }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <form method="GET" action="{{ route('admin.users.index') }}" role="search" class="relative w-full sm:w-auto">
                <label for="admin-search" class="sr-only">Search users by name or email</label>
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-ink-muted" />
                <input id="admin-search" type="search" name="q" placeholder="Search users by name or email" class="adm-input w-full pl-10 sm:w-[300px]">
            </form>
            <form method="GET" action="{{ route('admin.dashboard') }}" class="adm-seg" aria-label="Time range">
                @foreach($ranges as $key => [$label])
                    <button type="submit" name="range" value="{{ $key }}" aria-pressed="{{ $range === $key ? 'true' : 'false' }}" class="adm-seg-item">{{ $label }}</button>
                @endforeach
            </form>
        </div>
    </div>

    {{-- 4.2 Privacy --}}
    <div class="adm-strip items-center">
        <x-icon name="lock" class="h-5 w-5 flex-shrink-0 text-brand-navy" />
        <p><span class="font-semibold text-brand-navy">Aggregate view only.</span> Admins see counts and trends, never a user's amounts, notes or categories.</p>
    </div>

    {{-- 4.3 KPIs --}}
    <div class="grid gap-4 [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))]">
        @foreach($kpis as $kpi)
            <div class="flex flex-col gap-2 rounded-card bg-surface-card px-6 py-5">
                <p class="text-sm text-ink-muted">{{ $kpi['label'] }}</p>
                <p class="font-display text-[32px] font-semibold leading-[1.1] text-brand-navy">{{ $kpi['value'] }}</p>
                <p class="adm-caption">
                    @if($kpi['change'] !== null)
                        @php $up = $kpi['change'] >= 0; @endphp
                        <span class="font-semibold {{ $up ? 'text-status-successText' : 'text-status-error' }}">{{ $up ? '↑' : '↓' }} {{ \App\Support\Counts::percent($kpi['change']) }}%</span>
                        {{ $kpi['sub'] }}
                    @elseif(str_starts_with($kpi['sub'], 'vs'))
                        Nothing to compare with yet
                    @else
                        {{ $kpi['sub'] }}
                    @endif
                </p>
            </div>
        @endforeach
    </div>

    {{-- 4.4 Sign-ups + Platform --}}
    <div class="flex flex-wrap gap-4">
        <section class="adm-card flex-[2_1_520px] min-w-0">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="adm-h2">Sign-ups by month</h2>
                <p class="adm-caption">{{ $first['long'] }} to {{ $last['long'] }}</p>
            </div>

            <div class="mt-6 flex gap-2">
                {{-- Y axis --}}
                <div class="relative h-[200px] w-9 flex-shrink-0 text-right text-xs text-ink-muted" aria-hidden="true">
                    @for($i = 0; $i <= 4; $i++)
                        <span class="absolute right-1 -translate-y-1/2 leading-none" style="top: {{ $i * 25 }}%">{{ \App\Support\Counts::indian((int) round($signups['top'] - $i * $signups['step'])) }}</span>
                    @endfor
                </div>
                <div class="min-w-0 flex-1">
                    <div class="relative h-[200px]" role="img"
                         aria-label="Monthly sign-ups from {{ number_format($first['count']) }} in {{ $first['long'] }} to {{ number_format($last['count']) }} in {{ $last['long'] }}">
                        @for($i = 0; $i < 4; $i++)
                            <div class="absolute inset-x-0 border-t border-surface-page" style="top: {{ $i * 25 }}%"></div>
                        @endfor
                        <div class="absolute inset-x-0 bottom-0 border-t border-chart-4"></div>
                        <div class="absolute inset-0 flex items-end gap-2">
                            @foreach($bars as $i => $bar)
                                @php $current = $i === count($bars) - 1; @endphp
                                <div class="relative flex h-full flex-1 items-end">
                                    <div class="w-full rounded-t {{ $current ? 'bg-brand-cyan' : 'bg-brand-navy' }}"
                                         style="height: {{ round($bar['count'] / $signups['top'] * 200, 1) }}px"></div>
                                    @if($current)
                                        <span class="absolute left-1/2 -translate-x-1/2 text-xs font-semibold text-brand-navy"
                                              style="bottom: {{ round($bar['count'] / $signups['top'] * 200, 1) + 4 }}px">{{ \App\Support\Counts::indian($bar['count']) }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="mt-2 flex gap-2" aria-hidden="true">
                        @foreach($bars as $i => $bar)
                            <span class="flex-1 text-center text-xs {{ $i === count($bars) - 1 ? 'font-semibold text-brand-navy' : 'text-ink-muted' }}">{{ $bar['label'] }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section class="adm-card flex flex-[1_1_300px] flex-col gap-4">
            <h2 class="adm-h2">Platform</h2>
            @foreach($platforms as $p)
                <div>
                    <div class="flex items-center justify-between text-sm">
                        <span>{{ $p['label'] }}</span>
                        <span class="font-semibold text-brand-navy">{{ $p['share'] }}%</span>
                    </div>
                    <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-surface-page" role="progressbar" aria-label="{{ $p['label'] }}" aria-valuenow="{{ $p['share'] }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="h-full rounded-full {{ $fills[$p['label']] }}" style="width: {{ $p['share'] }}%"></div>
                    </div>
                </div>
            @endforeach
            @if($currencies)
                <div class="border-t border-surface-page pt-4">
                    <p class="adm-eyebrow text-ink-muted">Top currencies</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach($currencies as $c)
                            <span class="adm-chip">{{ $c['code'] }} {{ $c['share'] }}%</span>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
    </div>

    {{-- 4.5 Recent sign-ups + Needs attention --}}
    <div class="flex flex-wrap gap-4">
        <section class="adm-card flex-[2_1_520px] min-w-0">
            <div class="flex items-center justify-between gap-2">
                <h2 class="adm-h2">Recent sign-ups</h2>
                <a href="{{ route('admin.users.index') }}" class="adm-link">All users</a>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[600px] text-sm">
                    <thead>
                        <tr>
                            <th scope="col" class="adm-th">User</th>
                            <th scope="col" class="adm-th">Platform</th>
                            <th scope="col" class="adm-th">Joined</th>
                            <th scope="col" class="adm-th">Status</th>
                            <th scope="col" class="adm-th"><span class="sr-only">Action</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recent as $u)
                            <tr>
                                <td class="adm-td">
                                    <p class="font-semibold">{{ $u['name'] }}</p>
                                    <p class="adm-caption">{{ $u['masked'] }}</p>
                                </td>
                                <td class="adm-td">{{ $u['platform'] }}</td>
                                <td class="adm-td whitespace-nowrap">{{ $u['joined'] }}</td>
                                <td class="adm-td">
                                    @if($u['verified'])
                                        <span class="inline-flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-status-success" aria-hidden="true"></span>Active</span>
                                    @else
                                        <span class="adm-badge-medium">Not verified</span>
                                    @endif
                                </td>
                                <td class="adm-td text-right">
                                    <a href="{{ route('admin.users.index', ['q' => $u['email']]) }}" class="adm-link">View<span class="sr-only"> {{ $u['name'] }}</span></a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="adm-td py-8 text-center text-ink-muted">No accounts yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="adm-card flex flex-[1_1_300px] flex-col gap-4">
            <h2 class="adm-h2">Needs attention</h2>
            @forelse($attention as $item)
                <div class="flex items-start gap-3 border-b border-surface-page pb-4 last:border-0 last:pb-0">
                    <span class="adm-badge-{{ $item['level'] === 'high' ? 'high' : ($item['level'] === 'medium' ? 'medium' : 'low') }}">{{ ucfirst($item['level']) }}</span>
                    <div>
                        <p class="text-sm">{{ $item['text'] }}</p>
                        <a href="{{ $item['link'] }}" class="mt-1 inline-block text-[13px] font-semibold text-brand-navy hover:underline">{{ $item['cta'] }}</a>
                    </div>
                </div>
            @empty
                <p class="text-sm text-ink-muted">All clear. Nothing needs you right now.</p>
            @endforelse
        </section>
    </div>

    {{-- 4.6 Quick actions + Admin activity --}}
    <div class="flex flex-wrap gap-4">
        <section class="adm-card flex flex-[1_1_300px] flex-col gap-3">
            <h2 class="adm-h2">Quick actions</h2>
            <a href="{{ route('admin.notifications.index') }}" class="adm-btn-primary w-full"><x-icon name="megaphone" class="h-5 w-5" /> Send announcement</a>
            <button type="button" class="adm-btn-outline w-full" aria-disabled="true" title="Coming soon" disabled>Add default category</button>
            <button type="button" class="adm-btn-outline w-full" aria-disabled="true" title="Coming soon" disabled>Set minimum app version</button>
            <div class="mt-1 flex items-center justify-between gap-3 border-t border-surface-page pt-4">
                <div>
                    <p id="maintenance-label" class="text-sm font-semibold">Maintenance mode</p>
                    <p class="adm-caption">Off · app is open for everyone</p>
                </div>
                {{-- TODO: wire to maintenance mode once it is safe to. --}}
                <button type="button" role="switch" aria-checked="false" aria-labelledby="maintenance-label" aria-disabled="true" disabled
                        title="Coming soon" class="adm-switch cursor-not-allowed opacity-60"><span></span></button>
            </div>
        </section>

        <section class="adm-card flex-[2_1_520px] min-w-0">
            <div class="flex items-center justify-between gap-2">
                <h2 class="adm-h2">Admin activity</h2>
                <a href="{{ route('admin.activity.index') }}" class="adm-link">Full log</a>
            </div>
            <ul class="mt-3">
                @forelse($activity as $log)
                    <li class="flex items-start justify-between gap-4 border-b border-surface-page py-3 last:border-0">
                        <p class="text-sm"><span class="font-semibold">{{ $log->admin_id === auth()->id() ? 'You' : ($log->admin?->name ?: 'A former admin') }}</span> {!! $withIds($log->description) !!}</p>
                        <span class="adm-caption whitespace-nowrap">{{ \App\Http\Controllers\Admin\DashboardController::when($log->created_at) }}</span>
                    </li>
                @empty
                    <li class="py-3 text-sm text-ink-muted">Nothing yet. Role changes and sent notifications show up here.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-admin-layout>
