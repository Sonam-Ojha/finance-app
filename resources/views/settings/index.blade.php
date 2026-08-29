<x-app-layout>
    <x-slot name="title">Settings & Masters</x-slot>

    <div class="space-y-5" x-data="{ tab: '{{ session('_fragment') === 'income-cat' ? 'income' : (session('_fragment') === 'payment-modes' ? 'payment' : (session('_fragment') === 'invest-cat' ? 'invest' : (session('_fragment') === 'bank-masters' ? 'bank' : 'expense'))) }}' }">

        {{-- Page header --}}
        <div>
            <h2 class="text-lg font-bold text-slate-800">Settings & Masters</h2>
            <p class="text-xs text-slate-400 mt-0.5">Manage dropdown values used across the application</p>
        </div>

        {{-- Tab bar --}}
        <div class="bg-white rounded-2xl shadow-sm p-1.5 flex gap-1 overflow-x-auto">
            @foreach([
                ['key'=>'expense', 'label'=>'Expense Categories', 'color'=>'red',    'icon'=>'🛒', 'count'=> $expenseCategories->count()],
                ['key'=>'income',  'label'=>'Income Categories',   'color'=>'emerald','icon'=>'💰', 'count'=> $incomeCategories->count()],
                ['key'=>'payment', 'label'=>'Payment Modes',       'color'=>'blue',   'icon'=>'💳', 'count'=> $paymentModes->count()],
                ['key'=>'invest',  'label'=>'Investment Types',    'color'=>'indigo', 'icon'=>'📈', 'count'=> $investmentCategories->count()],
                ['key'=>'bank',    'label'=>'Banks',               'color'=>'amber',  'icon'=>'🏦', 'count'=> $bankMasters->count()],
            ] as $t)
            <button @click="tab='{{ $t['key'] }}'"
                    :class="tab==='{{ $t['key'] }}' ? 'bg-slate-900 text-white shadow' : 'text-slate-500 hover:bg-slate-50'"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium transition-all whitespace-nowrap flex-shrink-0">
                <span>{{ $t['icon'] }}</span>
                <span class="hidden sm:inline">{{ $t['label'] }}</span>
                <span class="hidden sm:inline text-xs opacity-60">({{ $t['count'] }})</span>
                <span class="sm:hidden">{{ $t['count'] }}</span>
            </button>
            @endforeach
        </div>

        {{-- ══ TAB: Expense Categories ══ --}}
        <div x-show="tab==='expense'" x-cloak id="expense-cat">
            <div class="grid lg:grid-cols-3 gap-4">

                {{-- Add form --}}
                <div class="bg-white rounded-2xl shadow-sm p-5">
                    <h3 class="font-semibold text-slate-700 mb-4 flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-red-100 flex items-center justify-center text-sm">🛒</span>
                        Add Expense Category
                    </h3>
                    <form method="POST" action="{{ route('settings.expense-category.store') }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Category Name *</label>
                            <input type="text" name="name" placeholder="e.g. Internet Bill" required
                                   class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-red-300 focus:border-red-400 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Group *</label>
                            <select name="group" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-red-300 outline-none">
                                <option value="home">🏠 Home</option>
                                <option value="personal">👤 Personal</option>
                                <option value="business">💼 Business</option>
                                <option value="other">📦 Other</option>
                            </select>
                        </div>
                        <button class="w-full bg-red-500 hover:bg-red-600 text-white py-2.5 rounded-xl text-sm font-semibold transition-colors">
                            + Add Category
                        </button>
                    </form>
                </div>

                {{-- List --}}
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="font-semibold text-slate-700">All Expense Categories</h3>
                        <span class="text-xs bg-red-100 text-red-600 px-2 py-0.5 rounded-full">{{ $expenseCategories->count() }} total</span>
                    </div>
                    @foreach($expenseCategories->groupBy('group') as $group => $cats)
                    <div class="px-5 py-2 border-b border-slate-50">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2 mt-1">
                            {{ ['home'=>'🏠 Home','personal'=>'👤 Personal','business'=>'💼 Business','other'=>'📦 Other'][$group] ?? $group }}
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                            @foreach($cats as $cat)
                            <div class="flex items-center justify-between bg-slate-50 rounded-xl px-3 py-2">
                                <span class="text-sm text-slate-700 font-medium">{{ $cat->name }}</span>
                                <div class="flex items-center gap-2">
                                    @if($cat->is_default)
                                        <span class="text-[10px] bg-blue-100 text-blue-500 px-1.5 py-0.5 rounded-full">default</span>
                                    @else
                                        <form method="POST" action="{{ route('settings.expense-category.destroy', $cat) }}" onsubmit="return confirm('Delete this category?')">
                                            @csrf @method('DELETE')
                                            <button class="text-red-400 hover:text-red-600 transition-colors p-1">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                    @if($expenseCategories->isEmpty())
                        <div class="text-center py-8 text-slate-400 text-sm">No categories yet</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ══ TAB: Income Categories ══ --}}
        <div x-show="tab==='income'" x-cloak id="income-cat">
            <div class="grid lg:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl shadow-sm p-5">
                    <h3 class="font-semibold text-slate-700 mb-4 flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-emerald-100 flex items-center justify-center text-sm">💰</span>
                        Add Income Category
                    </h3>
                    <form method="POST" action="{{ route('settings.income-category.store') }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Category Name *</label>
                            <input type="text" name="name" placeholder="e.g. Rental Income" required
                                   class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-300 focus:border-emerald-400 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Income Type *</label>
                            <select name="type" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-300 outline-none">
                                <option value="salary">💼 Salary</option>
                                <option value="lic_commission">🤝 LIC Commission</option>
                                <option value="business">🏪 Business</option>
                                <option value="received_from">📥 Received From Person</option>
                                <option value="other">📦 Other</option>
                            </select>
                        </div>
                        <button class="w-full bg-emerald-500 hover:bg-emerald-600 text-white py-2.5 rounded-xl text-sm font-semibold transition-colors">
                            + Add Category
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="font-semibold text-slate-700">All Income Categories</h3>
                        <span class="text-xs bg-emerald-100 text-emerald-600 px-2 py-0.5 rounded-full">{{ $incomeCategories->count() }} total</span>
                    </div>
                    @php
                    $typeLabels = ['salary'=>'💼 Salary','lic_commission'=>'🤝 LIC Commission','business'=>'🏪 Business','received_from'=>'📥 Received From','other'=>'📦 Other'];
                    @endphp
                    @foreach($incomeCategories->groupBy('type') as $type => $cats)
                    <div class="px-5 py-2 border-b border-slate-50">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2 mt-1">{{ $typeLabels[$type] ?? $type }}</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                            @foreach($cats as $cat)
                            <div class="flex items-center justify-between bg-slate-50 rounded-xl px-3 py-2">
                                <span class="text-sm text-slate-700 font-medium">{{ $cat->name }}</span>
                                <div class="flex items-center gap-2">
                                    @if($cat->is_default)
                                        <span class="text-[10px] bg-blue-100 text-blue-500 px-1.5 py-0.5 rounded-full">default</span>
                                    @else
                                        <form method="POST" action="{{ route('settings.income-category.destroy', $cat) }}" onsubmit="return confirm('Delete?')">
                                            @csrf @method('DELETE')
                                            <button class="text-red-400 hover:text-red-600 p-1">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                    @if($incomeCategories->isEmpty())
                        <div class="text-center py-8 text-slate-400 text-sm">No income categories yet</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ══ TAB: Payment Modes ══ --}}
        <div x-show="tab==='payment'" x-cloak id="payment-modes">
            <div class="grid lg:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl shadow-sm p-5">
                    <h3 class="font-semibold text-slate-700 mb-4 flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center text-sm">💳</span>
                        Add Payment Mode
                    </h3>
                    <form method="POST" action="{{ route('settings.payment-mode.store') }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Mode Name *</label>
                            <input type="text" name="name" placeholder="e.g. PhonePe, NEFT" required
                                   class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-300 focus:border-blue-400 outline-none">
                        </div>
                        <button class="w-full bg-blue-500 hover:bg-blue-600 text-white py-2.5 rounded-xl text-sm font-semibold transition-colors">
                            + Add Mode
                        </button>
                    </form>

                    <div class="mt-5 p-3 bg-blue-50 rounded-xl">
                        <p class="text-xs text-blue-600 font-medium">💡 Tip</p>
                        <p class="text-xs text-blue-500 mt-1">These payment modes appear in Income and Expense forms as dropdown options.</p>
                    </div>
                </div>

                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="font-semibold text-slate-700">All Payment Modes</h3>
                        <span class="text-xs bg-blue-100 text-blue-600 px-2 py-0.5 rounded-full">{{ $paymentModes->count() }} total</span>
                    </div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @forelse($paymentModes as $pm)
                        <div class="flex items-center justify-between bg-slate-50 rounded-xl px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center text-sm">
                                    @php
                                    $icons = ['Cash'=>'💵','UPI'=>'📱','Bank Transfer'=>'🏦','Cheque'=>'📄','Credit Card'=>'💳','Debit Card'=>'💳','Online'=>'🌐','Other'=>'📦'];
                                    echo $icons[$pm->name] ?? '💳';
                                    @endphp
                                </div>
                                <span class="text-sm text-slate-700 font-medium">{{ $pm->name }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                @if($pm->is_default)
                                    <span class="text-[10px] bg-blue-100 text-blue-500 px-1.5 py-0.5 rounded-full">default</span>
                                @else
                                    <form method="POST" action="{{ route('settings.payment-mode.destroy', $pm) }}" onsubmit="return confirm('Delete?')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-400 hover:text-red-600 p-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        @empty
                            <div class="col-span-2 text-center py-8 text-slate-400 text-sm">No payment modes yet</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ TAB: Investment Categories ══ --}}
        <div x-show="tab==='invest'" x-cloak id="invest-cat">
            <div class="grid lg:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl shadow-sm p-5">
                    <h3 class="font-semibold text-slate-700 mb-4 flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-indigo-100 flex items-center justify-center text-sm">📈</span>
                        Add Investment Type
                    </h3>
                    <form method="POST" action="{{ route('settings.investment-category.store') }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Type Name *</label>
                            <input type="text" name="name" placeholder="e.g. Sovereign Gold Bond" required
                                   class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">
                        </div>
                        <button class="w-full bg-indigo-500 hover:bg-indigo-600 text-white py-2.5 rounded-xl text-sm font-semibold transition-colors">
                            + Add Type
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="font-semibold text-slate-700">All Investment Types</h3>
                        <span class="text-xs bg-indigo-100 text-indigo-600 px-2 py-0.5 rounded-full">{{ $investmentCategories->count() }} total</span>
                    </div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @forelse($investmentCategories as $ic)
                        <div class="flex items-center justify-between bg-slate-50 rounded-xl px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center text-sm">
                                    @php
                                    $icons = ['LIC Policy'=>'📋','Mutual Fund'=>'📊','Stocks / Equity'=>'📈','Fixed Deposit (FD)'=>'🏦','Recurring Deposit (RD)'=>'🔄','Gold / Jewellery'=>'🥇','Real Estate'=>'🏠','PPF'=>'📑','NPS'=>'👴','Crypto'=>'🪙','Other'=>'📦'];
                                    echo $icons[$ic->name] ?? '📈';
                                    @endphp
                                </div>
                                <span class="text-sm text-slate-700 font-medium">{{ $ic->name }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                @if($ic->is_default)
                                    <span class="text-[10px] bg-blue-100 text-blue-500 px-1.5 py-0.5 rounded-full">default</span>
                                @else
                                    <form method="POST" action="{{ route('settings.investment-category.destroy', $ic) }}" onsubmit="return confirm('Delete?')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-400 hover:text-red-600 p-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        @empty
                            <div class="col-span-2 text-center py-8 text-slate-400 text-sm">No investment types yet</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ TAB: Bank Masters ══ --}}
        <div x-show="tab==='bank'" x-cloak id="bank-masters">
            <div class="grid lg:grid-cols-3 gap-4">

                {{-- Add form --}}
                <div class="bg-white rounded-2xl shadow-sm p-5">
                    <h3 class="font-semibold text-slate-700 mb-4 flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-amber-100 flex items-center justify-center text-sm">🏦</span>
                        Add Bank
                    </h3>
                    <form method="POST" action="{{ route('settings.bank-master.store') }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Bank Name *</label>
                            <input type="text" name="name" placeholder="e.g. HDFC Bank" required
                                   class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-300 focus:border-amber-400 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Short Name / Abbreviation</label>
                            <input type="text" name="short_name" placeholder="e.g. HDFC" maxlength="20"
                                   class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-300 focus:border-amber-400 outline-none">
                        </div>
                        <button class="w-full bg-amber-500 hover:bg-amber-600 text-white py-2.5 rounded-xl text-sm font-semibold transition-colors">
                            + Add Bank
                        </button>
                    </form>

                    <div class="mt-5 p-3 bg-amber-50 rounded-xl">
                        <p class="text-xs text-amber-700 font-medium">💡 Tip</p>
                        <p class="text-xs text-amber-600 mt-1">These bank names appear as dropdown options when adding a bank account.</p>
                    </div>
                </div>

                {{-- List --}}
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="font-semibold text-slate-700">All Banks</h3>
                        <span class="text-xs bg-amber-100 text-amber-600 px-2 py-0.5 rounded-full">{{ $bankMasters->count() }} total</span>
                    </div>
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @forelse($bankMasters as $bm)
                        <div class="flex items-center justify-between bg-slate-50 rounded-xl px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-9 h-9 rounded-lg bg-amber-100 flex items-center justify-center text-sm font-bold text-amber-700 flex-shrink-0">
                                    {{ $bm->short_name ? strtoupper(substr($bm->short_name, 0, 2)) : '🏦' }}
                                </div>
                                <div>
                                    <div class="text-sm text-slate-700 font-medium leading-tight">{{ $bm->name }}</div>
                                    @if($bm->short_name)
                                        <div class="text-[11px] text-slate-400">{{ $bm->short_name }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                @if($bm->is_default)
                                    <span class="text-[10px] bg-blue-100 text-blue-500 px-1.5 py-0.5 rounded-full">default</span>
                                @else
                                    <form method="POST" action="{{ route('settings.bank-master.destroy', $bm) }}" onsubmit="return confirm('Delete this bank?')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-400 hover:text-red-600 transition-colors p-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        @empty
                            <div class="col-span-2 text-center py-8 text-slate-400 text-sm">No banks added yet</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
