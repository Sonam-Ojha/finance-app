<x-admin-layout>
    <x-slot name="title">Users</x-slot>

    <x-slot name="subtitle">
        {{ \App\Support\Counts::indian($users->total()) }} {{ \Illuminate\Support\Str::plural('account', $users->total()) }}{{ $status ? ' not verified' : '' }}
        · {{ $adminCount }} {{ \Illuminate\Support\Str::plural('admin', $adminCount) }}
    </x-slot>
    <x-slot name="actions">
        <form method="GET" action="{{ route('admin.users.index') }}" role="search" class="flex flex-wrap items-center gap-2">
            @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
            <label for="user-search" class="sr-only">Search users by name or email</label>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-ink-muted" />
                <input id="user-search" type="search" name="q" value="{{ $search }}" placeholder="Search users by name or email" class="adm-input w-full pl-10 sm:w-[300px]">
            </div>
            <button type="submit" class="adm-btn-primary !h-11">Search</button>
        </form>
    </x-slot>

    <nav class="adm-seg self-start" aria-label="Filter">
        <a href="{{ route('admin.users.index', array_filter(['q' => $search])) }}" class="adm-seg-item" @unless($status) aria-current="page" @endunless>Everyone</a>
        <a href="{{ route('admin.users.index', array_filter(['q' => $search, 'status' => 'unverified'])) }}" class="adm-seg-item" @if($status) aria-current="page" @endif>Not verified</a>
    </nav>

    <section class="adm-card min-w-0">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <thead>
                    <tr>
                        <th scope="col" class="adm-th">User</th>
                        <th scope="col" class="adm-th">Role</th>
                        <th scope="col" class="adm-th">Status</th>
                        <th scope="col" class="adm-th">Phones</th>
                        <th scope="col" class="adm-th">Joined</th>
                        <th scope="col" class="adm-th text-right">Admin access</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td class="adm-td">
                                <div class="flex items-center gap-3">
                                    <x-admin.avatar :user="$user" />
                                    <div class="min-w-0">
                                        <p class="font-semibold">{{ $user->name ?: '—' }} <span class="font-mono text-[13px] font-normal text-ink-muted">#{{ $user->id }}</span></p>
                                        <p class="adm-caption">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="adm-td">
                                @if($user->isAdmin())
                                    <span class="adm-badge bg-brand-navy text-white">Admin</span>
                                @else
                                    <span class="adm-badge-low">User</span>
                                @endif
                            </td>
                            <td class="adm-td">
                                @if($user->email_verified_at)
                                    <span class="inline-flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-status-success" aria-hidden="true"></span>Active</span>
                                @else
                                    <span class="adm-badge-medium">Not verified</span>
                                @endif
                            </td>
                            <td class="adm-td">{{ $user->device_tokens_count }}</td>
                            <td class="adm-td whitespace-nowrap text-ink-muted">{{ $user->created_at?->timezone(config('app.display_timezone'))->format('j M Y') }}</td>
                            <td class="adm-td whitespace-nowrap text-right">
                                @if($user->is(auth()->user()))
                                    <span class="adm-caption">You</span>
                                @elseif($user->isAdmin())
                                    <form method="POST" action="{{ route('admin.users.role', $user) }}"
                                          data-confirm-title="Remove admin access?"
                                          data-confirm-message="{{ $user->email }} will go back to being an ordinary user."
                                          data-confirm-button="Remove admin">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="role" value="user">
                                        <button class="min-h-11 px-2 text-sm font-semibold text-status-error hover:underline">Remove admin</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.users.role', $user) }}"
                                          data-confirm-title="Make this user an admin?"
                                          data-confirm-message="{{ $user->email }} will be able to send notifications and manage admins."
                                          data-confirm-button="Make admin">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="role" value="admin">
                                        <button class="adm-btn-outline !h-11 !px-4 !text-sm">Make admin</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="adm-td py-10 text-center text-ink-muted">
                                {{ $search !== '' ? "No user matches \"{$search}\"." : ($status ? 'Everyone has verified their email.' : 'No users yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
            <div class="mt-4">{{ $users->links() }}</div>
        @endif
    </section>
</x-admin-layout>
