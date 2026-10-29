<x-admin-layout>
    <x-slot name="title">Users</x-slot>

    <x-slot name="subtitle">
        {{ \App\Support\Counts::indian($users->total()) }} {{ \Illuminate\Support\Str::plural('account', $users->total()) }}{{ ['unverified' => ' not verified', 'blocked' => ' blocked'][$status] ?? '' }}
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
        <a href="{{ route('admin.users.index', array_filter(['q' => $search, 'status' => 'unverified'])) }}" class="adm-seg-item" @if($status === 'unverified') aria-current="page" @endif>Not verified</a>
        <a href="{{ route('admin.users.index', array_filter(['q' => $search, 'status' => 'blocked'])) }}" class="adm-seg-item" @if($status === 'blocked') aria-current="page" @endif>Blocked</a>
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
                        <th scope="col" class="adm-th text-right">Actions</th>
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
                                @if($user->isBlocked())
                                    <span class="adm-badge-high" title="Blocked {{ $user->blocked_at->timezone(config('app.display_timezone'))->format('j M Y') }}">Blocked</span>
                                @elseif($user->email_verified_at)
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
                                @elseif($user->isBlocked())
                                    {{-- Security: blocked accounts can only be unblocked here --}}
                                    <form method="POST" action="{{ route('admin.users.unblock', $user) }}"
                                          data-confirm-title="Unblock this user?"
                                          data-confirm-message="{{ $user->email }} will be able to sign in again."
                                          data-confirm-button="Unblock">
                                        @csrf @method('DELETE')
                                        <button class="adm-btn-outline !h-11 !px-4 !text-sm">Unblock</button>
                                    </form>
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
                                    <div class="inline-flex items-center gap-1">
                                        <form method="POST" action="{{ route('admin.users.role', $user) }}"
                                              data-confirm-title="Make this user an admin?"
                                              data-confirm-message="{{ $user->email }} will be able to send notifications and manage admins."
                                              data-confirm-button="Make admin">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="role" value="admin">
                                            <button class="adm-btn-outline !h-11 !px-4 !text-sm">Make admin</button>
                                        </form>
                                        {{-- Security: block (admins must lose admin access first) --}}
                                        <form method="POST" action="{{ route('admin.users.block', $user) }}"
                                              data-confirm-title="Block this user?"
                                              data-confirm-message="{{ $user->email }} will be signed out of the app and the website everywhere, and will not be able to sign in until unblocked."
                                              data-confirm-button="Block">
                                            @csrf
                                            <button class="min-h-11 px-2 text-sm font-semibold text-status-error hover:underline">Block</button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="adm-td py-10 text-center text-ink-muted">
                                {{ $search !== '' ? "No user matches \"{$search}\"." : (['unverified' => 'Everyone has verified their email.', 'blocked' => 'Nobody is blocked.'][$status] ?? 'No users yet.') }}
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
