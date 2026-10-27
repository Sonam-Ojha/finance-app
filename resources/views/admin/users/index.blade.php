<x-app-layout>
    <x-slot name="title">Users</x-slot>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-slate-800">Users</h2>
                <p class="text-xs text-slate-500">{{ $users->total() }} {{ \Illuminate\Support\Str::plural('account', $users->total()) }} · {{ $adminCount }} {{ \Illuminate\Support\Str::plural('admin', $adminCount) }}</p>
            </div>
            <form method="GET" action="{{ route('admin.users.index') }}" class="flex gap-2">
                <input type="search" name="q" value="{{ $search }}" placeholder="Search name or email"
                       class="w-56 border border-gray-300 rounded-lg px-3 py-2 text-sm" aria-label="Search users">
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700">Search</button>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm table-wrap">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-100">
                        <th class="px-4 py-3 font-semibold">User</th>
                        <th class="px-4 py-3 font-semibold">Role</th>
                        <th class="px-4 py-3 font-semibold">Phones</th>
                        <th class="px-4 py-3 font-semibold">Joined</th>
                        <th class="px-4 py-3 font-semibold text-right">Admin access</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-800">{{ $user->name ?: '—' }}</p>
                                <p class="text-xs text-slate-500">{{ $user->email }}</p>
                            </td>
                            <td class="px-4 py-3">
                                @if($user->isAdmin())
                                    <span class="inline-flex items-center gap-1 bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-md text-xs font-semibold">Admin</span>
                                @else
                                    <span class="inline-flex items-center bg-slate-100 text-slate-600 px-2 py-0.5 rounded-md text-xs font-medium">User</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $user->device_tokens_count }}</td>
                            <td class="px-4 py-3 text-slate-500 whitespace-nowrap">{{ $user->created_at?->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if($user->is(auth()->user()))
                                    <span class="text-xs text-slate-400">You</span>
                                @elseif($user->isAdmin())
                                    <form method="POST" action="{{ route('admin.users.role', $user) }}"
                                          onsubmit="return confirm('Remove admin access for {{ $user->email }}?')">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="role" value="user">
                                        <button class="text-xs font-semibold text-red-600 hover:underline">Remove admin</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.users.role', $user) }}"
                                          onsubmit="return confirm('Make {{ $user->email }} an admin? They will be able to send notifications and manage admins.')">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="role" value="admin">
                                        <button class="bg-indigo-50 text-indigo-700 px-3 py-1.5 rounded-lg text-xs font-semibold hover:bg-indigo-100">Make admin</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-slate-400">
                                {{ $search !== '' ? "No user matches \"{$search}\"." : 'No users yet.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $users->links() }}
    </div>
</x-app-layout>
