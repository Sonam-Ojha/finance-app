<x-app-layout>
    <x-slot name="title">{{ $group->name }}</x-slot>
    <x-slot name="heading">{{ $group->label() }}</x-slot>
    @php
        $mine  = collect($people)->firstWhere('user_id', $me)['balance'] ?? 0;
        $count = $group->members->count();
        // "You" for the one reading; names for everyone else.
        $who   = fn (int $id) => $id === $me ? 'You' : ($names[$id] ?? \App\Support\Groups::name(null));
        $whoIn = fn (int $id) => $id === $me ? 'you' : ($names[$id] ?? \App\Support\Groups::name(null));
        $settleFields = ['from_user_id', 'to_user_id', 'amount', 'amount_minor', 'on'];
        $firstOther = collect($people)->first(fn ($p) => $p['user_id'] !== $me)['user_id'] ?? null;
        $pad = mb_strlen($money->symbol) > 1 ? '!pl-12' : '!pl-8';
    @endphp
    <x-slot name="subtitle">
        {{ $count }} {{ Str::plural('person', $count) }} ·
        @if($mine > 0) you are owed {{ $money->format($mine) }}
        @elseif($mine < 0) you owe {{ $money->format(-$mine) }}
        @else you are settled up
        @endif
    </x-slot>
    <x-slot name="actions">
        <a href="{{ route('groups.edit', $group) }}" class="btn-secondary"><x-icon name="pencil" class="h-4 w-4" /> Edit</a>
        <a href="{{ route('groups.expenses.create', $group) }}" class="btn-primary"><x-icon name="plus" class="h-5 w-5" /> Add expense</a>
    </x-slot>

    <div class="grid gap-4 lg:grid-cols-2">

        {{-- Balances --}}
        <section class="card" aria-labelledby="balances-title">
            <h3 id="balances-title" class="card-title">Balances</h3>
            <div class="mt-2 divide-soft">
                @foreach($people as $person)
                    @php $you = $person['user_id'] === $me; @endphp
                    <div class="flex items-center gap-3 py-2.5">
                        @if($person['user'])
                            <x-admin.avatar :user="$person['user']" size="h-9 w-9" />
                        @else
                            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-surface-page text-[13px] font-semibold text-ink-muted dark:bg-night-line dark:text-night-muted" aria-hidden="true">?</span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium strong">{{ $you ? 'You' : $person['name'] }}</p>
                            @unless($person['member'])<p class="text-xs muted">No longer in the group</p>@endunless
                        </div>
                        @if($person['balance'] > 0)
                            <p class="text-right text-sm"><span class="block text-xs muted">{{ $you ? 'You get back' : 'Gets back' }}</span><span class="font-semibold money-in">{{ $money->format($person['balance']) }}</span></p>
                        @elseif($person['balance'] < 0)
                            <p class="text-right text-sm"><span class="block text-xs muted">{{ $you ? 'You owe' : 'Owes' }}</span><span class="font-semibold money-out">{{ $money->format(-$person['balance']) }}</span></p>
                        @else
                            <p class="text-sm muted">Settled up</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Settle up --}}
        <section class="card" aria-labelledby="settle-title" x-data="{ custom: @js($errors->hasAny($settleFields)) }">
            <div class="flex items-center justify-between gap-3">
                <h3 id="settle-title" class="card-title">Settle up</h3>
                <button type="button" class="link text-sm" @click="custom = ! custom" :aria-expanded="custom" aria-controls="record-payment">Record a payment</button>
            </div>

            @if(empty($suggestions))
                <p class="mt-3 text-sm muted">Everyone is settled up. Nobody owes anything.</p>
            @else
                <p class="mt-1 text-xs muted">The fewest payments that square everyone up.</p>
                <div class="mt-2 divide-soft">
                    @foreach($suggestions as $s)
                        @php $line = $who($s['from']) . ' paid ' . $whoIn($s['to']) . ' ' . $money->format($s['amount']); @endphp
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2 py-2.5">
                            <p class="min-w-0 flex-1 text-sm strong">
                                <span class="font-semibold">{{ $who($s['from']) }}</span> {{ $s['from'] === $me ? 'pay' : 'pays' }} <span class="font-semibold">{{ $whoIn($s['to']) }}</span>
                            </p>
                            <span class="font-semibold {{ $s['from'] === $me ? 'money-out' : ($s['to'] === $me ? 'money-in' : 'strong') }}">{{ $money->format($s['amount']) }}</span>
                            <form method="POST" action="{{ route('groups.settlements.store', $group) }}">
                                @csrf
                                <input type="hidden" name="from_user_id" value="{{ $s['from'] }}">
                                <input type="hidden" name="to_user_id" value="{{ $s['to'] }}">
                                <input type="hidden" name="amount_minor" value="{{ $s['amount'] }}">
                                <button type="submit" class="btn-secondary !min-h-9 !px-3 !text-sm" aria-label="Record: {{ $line }}">Mark paid</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif

            <form id="record-payment" x-show="custom" x-cloak method="POST" action="{{ route('groups.settlements.store', $group) }}"
                  class="mt-4 space-y-4 border-t border-surface-page pt-4 dark:border-night-line">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="from_user_id" class="field-label">Who paid</label>
                        <select id="from_user_id" name="from_user_id" class="input">
                            @foreach($people as $person)
                                <option value="{{ $person['user_id'] }}" @selected((int) old('from_user_id', $me) === $person['user_id'])>{{ $who($person['user_id']) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="to_user_id" class="field-label">Paid to</label>
                        <select id="to_user_id" name="to_user_id" class="input">
                            @foreach($people as $person)
                                <option value="{{ $person['user_id'] }}" @selected((int) old('to_user_id', $firstOther) === $person['user_id'])>{{ $who($person['user_id']) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="settle-amount" class="field-label">Amount</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm muted">{{ $money->symbol }}</span>
                            <input id="settle-amount" name="amount" type="text" inputmode="decimal" autocomplete="off" placeholder="0"
                                   value="{{ old('amount') }}" class="input {{ $pad }}">
                        </div>
                    </div>
                    <div>
                        <label for="settle-on" class="field-label">Date</label>
                        <input id="settle-on" name="on" type="date" max="{{ $today }}" value="{{ old('on', $today) }}" class="input">
                    </div>
                </div>
                @foreach($settleFields as $field)
                    @error($field)<p class="field-error" role="alert">{{ $message }}</p>@enderror
                @endforeach
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" @click="custom = false">Cancel</button>
                    <button type="submit" class="btn-primary">Record payment</button>
                </div>
            </form>

            @if($settlements->isNotEmpty())
                <h4 class="section-label mt-5">Recent payments</h4>
                <div class="mt-1 divide-soft">
                    @foreach($settlements as $settlement)
                        <div class="flex items-center justify-between gap-3 py-2 text-sm">
                            <p class="min-w-0 truncate strong">{{ $who($settlement->from_user_id) }} paid {{ $whoIn($settlement->to_user_id) }}</p>
                            <p class="flex-shrink-0 text-right">
                                <span class="font-semibold strong">{{ $money->format($settlement->amount_minor) }}</span>
                                <span class="text-xs muted">· {{ \App\Support\Labels::dayHeading($settlement->settled_on) }}</span>
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Expenses --}}
        <section class="card" id="expenses" aria-labelledby="expenses-title">
            <div class="flex items-center justify-between gap-3">
                <h3 id="expenses-title" class="card-title">Expenses</h3>
                <a href="{{ route('groups.expenses.create', $group) }}" class="link text-sm">+ Add expense</a>
            </div>

            @if($expenses->isEmpty())
                <div class="py-10 text-center">
                    <p class="font-semibold strong">No expenses yet</p>
                    <p class="mt-1 text-sm muted">Add what anyone paid for the group — dinner, tickets, rent — and split it.</p>
                </div>
            @else
                <div class="mt-2 divide-soft">
                    @foreach($expenses as $expense)
                        @php
                            $share = $expense->shareOf($me);
                            $day   = $expense->spent_on;
                            $otherYear = $day->year !== \App\Support\Labels::now()->year;
                        @endphp
                        <div class="flex items-center gap-2 py-2.5">
                            <a href="{{ route('groups.expenses.edit', [$group, $expense]) }}"
                               class="-mx-2 flex min-w-0 flex-1 items-center gap-3 rounded-xl px-2 py-1 hover:bg-surface-page dark:hover:bg-white/5">
                                <span class="flex h-11 w-11 flex-shrink-0 flex-col items-center justify-center rounded-card bg-surface-page leading-none dark:bg-night-line"
                                      title="{{ $day->format('j F Y') }}">
                                    <span class="text-[10px] font-semibold uppercase muted">{{ $day->format($otherYear ? "M 'y" : 'M') }}</span>
                                    <span class="mt-1 text-base font-bold strong">{{ $day->format('j') }}</span>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium strong">{{ $expense->description }}</span>
                                    <span class="block truncate text-xs muted">{{ $who($expense->paid_by) }} paid · {{ $expense->split === 'exact' ? 'exact amounts' : 'split equally' }}</span>
                                </span>
                                <span class="flex-shrink-0 text-right">
                                    <span class="block text-sm font-semibold strong">{{ $money->format($expense->amount_minor) }}</span>
                                    <span class="block text-xs muted">{{ $share ? 'Your share ' . $money->format($share) : 'Not your share' }}</span>
                                </span>
                            </a>
                            <form method="POST" action="{{ route('groups.expenses.destroy', [$group, $expense]) }}"
                                  data-confirm-title="Delete expense?"
                                  data-confirm-message="Remove {{ $expense->description }} ({{ $money->format($expense->amount_minor) }}) for everyone in the group?"
                                  data-confirm-button="Delete">
                                @csrf @method('DELETE')
                                <button type="submit" class="rounded-lg p-2 text-ink-muted hover:bg-red-50 hover:text-red-600 dark:text-night-muted dark:hover:bg-red-500/10" aria-label="Delete {{ $expense->description }}">
                                    <x-icon name="trash" class="h-4 w-4" />
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
                @if($expenses->hasPages())
                    <div class="mt-4">{{ $expenses->links() }}</div>
                @endif
            @endif
        </section>

        {{-- Members --}}
        <section class="card" id="members" aria-labelledby="members-title">
            <div class="flex items-center justify-between gap-3">
                <h3 id="members-title" class="card-title">Members</h3>
                <span class="text-sm muted">{{ $count }}</span>
            </div>
            @error('member')<p class="field-error" role="alert">{{ $message }}</p>@enderror

            <div class="mt-2 divide-soft">
                @foreach($group->members as $member)
                    @php
                        $self = $member->user_id === $me;
                        $name = \App\Support\Groups::name($member->user);
                    @endphp
                    <div class="flex items-center gap-3 py-2.5">
                        <x-admin.avatar :user="$member->user" size="h-9 w-9" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium strong">
                                {{ $name }}{{ $self ? ' (you)' : '' }}
                                @if($member->user_id === $group->created_by)
                                    <span class="ml-1 rounded-full bg-brand-tint px-2 py-0.5 text-[11px] font-semibold text-brand-navy dark:bg-brand-cyan/15 dark:text-brand-cyan">Made the group</span>
                                @endif
                            </p>
                            <p class="truncate text-xs muted">{{ $member->user->email }}</p>
                        </div>
                        <form method="POST" action="{{ route('groups.members.destroy', [$group, $member->user_id]) }}"
                              data-confirm-title="{{ $self ? "Leave {$group->name}?" : "Remove {$name}?" }}"
                              data-confirm-message="{{ $self ? 'You will no longer see this group. Anyone in it can add you back.' : "{$name} will no longer see this group. Expenses they were part of stay." }}"
                              data-confirm-button="{{ $self ? 'Leave' : 'Remove' }}">
                            @csrf @method('DELETE')
                            @if($self)
                                <button type="submit" class="btn-danger-ghost !min-h-9 !px-3 !text-sm">Leave</button>
                            @else
                                <button type="submit" class="rounded-lg p-2 text-ink-muted hover:bg-red-50 hover:text-red-600 dark:text-night-muted dark:hover:bg-red-500/10" aria-label="Remove {{ $name }}">
                                    <x-icon name="x" class="h-4 w-4" />
                                </button>
                            @endif
                        </form>
                    </div>
                @endforeach
            </div>

            <form method="POST" action="{{ route('groups.members.store', $group) }}" class="mt-4 border-t border-surface-page pt-4 dark:border-night-line">
                @csrf
                <label for="member-email" class="field-label">Add someone by email</label>
                <div class="flex gap-2">
                    <input id="member-email" name="email" type="email" required autocomplete="off" maxlength="255"
                           value="{{ old('email') }}" placeholder="friend@example.com" class="input">
                    <button type="submit" class="btn-primary flex-shrink-0">Add</button>
                </div>
                @error('email')
                    <p class="field-error">{{ $message }}</p>
                @else
                    <p class="mt-1 text-xs muted">They need an Expense Tracker account with this email.</p>
                @enderror
            </form>
        </section>
    </div>

    @if($canDelete)
        <form method="POST" action="{{ route('groups.destroy', $group) }}" class="text-center"
              data-confirm-title="Delete {{ $group->name }}?"
              data-confirm-message="Every expense and payment in it is removed for everyone in the group. This cannot be undone."
              data-confirm-button="Delete group">
            @csrf @method('DELETE')
            <button type="submit" class="btn-danger-ghost"><x-icon name="trash" class="h-4 w-4" /> Delete group</button>
        </form>
    @endif
</x-app-layout>
