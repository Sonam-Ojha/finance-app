<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>

    <div class="space-y-5">

        {{-- Page heading (mobile only – desktop shows in topbar) --}}
        <div class="lg:hidden">
            <h1 class="text-lg font-bold text-slate-800">Dashboard</h1>
            <p class="text-xs text-slate-400">{{ now()->format('l, d M Y') }}</p>
        </div>

        {{-- ── Row 1: key stats ── --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="rounded-2xl p-4 text-white shadow" style="background:linear-gradient(135deg,#10b981,#059669)">
                <p class="text-xs font-medium opacity-80">This Month Income</p>
                <p class="text-xl sm:text-2xl font-bold mt-1 truncate">₹{{ number_format($totalIncome,0) }}</p>
            </div>
            <div class="rounded-2xl p-4 text-white shadow" style="background:linear-gradient(135deg,#ef4444,#dc2626)">
                <p class="text-xs font-medium opacity-80">This Month Expense</p>
                <p class="text-xl sm:text-2xl font-bold mt-1 truncate">₹{{ number_format($totalExpense,0) }}</p>
            </div>
            <div class="rounded-2xl p-4 text-white shadow" style="background:linear-gradient(135deg,#3b82f6,#2563eb)">
                <p class="text-xs font-medium opacity-80">Bank Balance</p>
                <p class="text-xl sm:text-2xl font-bold mt-1 truncate">₹{{ number_format($bankBalance,0) }}</p>
            </div>
            <div class="rounded-2xl p-4 text-white shadow" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)">
                <p class="text-xs font-medium opacity-80">Net Savings</p>
                <p class="text-xl sm:text-2xl font-bold mt-1 truncate">₹{{ number_format($totalIncome-$totalExpense,0) }}</p>
            </div>
        </div>

        {{-- ── Row 2: secondary stats ── --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="bg-white rounded-2xl p-4 shadow-sm border-t-4 border-orange-400">
                <p class="text-xs text-slate-500">CC Outstanding</p>
                <p class="text-lg font-bold text-orange-500 mt-1 truncate">₹{{ number_format($creditOutstanding,0) }}</p>
            </div>
            <div class="bg-white rounded-2xl p-4 shadow-sm border-t-4 border-red-400">
                <p class="text-xs text-slate-500">Loan Pending</p>
                <p class="text-lg font-bold text-red-500 mt-1 truncate">₹{{ number_format($loanPending,0) }}</p>
            </div>
            <div class="bg-white rounded-2xl p-4 shadow-sm border-t-4 border-emerald-400">
                <p class="text-xs text-slate-500">Investment Value</p>
                <p class="text-lg font-bold text-emerald-600 mt-1 truncate">₹{{ number_format($investmentValue,0) }}</p>
            </div>
            <div class="bg-white rounded-2xl p-4 shadow-sm border-t-4 border-amber-400">
                <p class="text-xs text-slate-500">Pending Receivables</p>
                <p class="text-lg font-bold text-amber-600 mt-1 truncate">₹{{ number_format($pendingReceivables,0) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="bg-white rounded-2xl p-4 shadow-sm border-t-4 border-teal-400">
                <p class="text-xs text-slate-500">Commission (Month)</p>
                <p class="text-lg font-bold text-teal-600 mt-1 truncate">₹{{ number_format($commissionReceived,0) }}</p>
            </div>
            <div class="bg-white rounded-2xl p-4 shadow-sm border-t-4 border-pink-400">
                <p class="text-xs text-slate-500">Cashback (Month)</p>
                <p class="text-lg font-bold text-pink-600 mt-1 truncate">₹{{ number_format($cashbackReceived,0) }}</p>
            </div>
        </div>

        {{-- ── Charts ── --}}
        <div class="grid md:grid-cols-2 gap-4">
            <div class="bg-white rounded-2xl p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-700 mb-3">Income vs Expense (6 Months)</h3>
                <canvas id="incomeExpenseChart"></canvas>
            </div>
            <div class="bg-white rounded-2xl p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-700 mb-3">Category-wise Expense</h3>
                <canvas id="categoryChart"></canvas>
            </div>
        </div>

        {{-- ── Reminders + Transactions ── --}}
        <div class="grid md:grid-cols-2 gap-4">
            <div class="bg-white rounded-2xl p-4 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-slate-700">Upcoming Reminders</h3>
                    <a href="{{ route('reminder.index') }}" class="text-xs text-indigo-600 hover:underline font-medium">View all →</a>
                </div>
                <div class="space-y-1">
                @forelse($reminders as $r)
                    <div class="flex items-center justify-between py-2.5 border-b border-slate-100 last:border-0">
                        <div class="min-w-0 pr-2">
                            <p class="text-sm font-medium text-slate-700 truncate">{{ $r->title }}</p>
                            <p class="text-xs text-slate-400">{{ $r->due_date->format('d M Y') }}</p>
                        </div>
                        @if($r->amount)
                            <span class="text-sm font-semibold text-red-500 flex-shrink-0">₹{{ number_format($r->amount,0) }}</span>
                        @endif
                    </div>
                @empty
                    <div class="text-center py-6 text-slate-400">
                        <div class="text-3xl mb-1">🎉</div>
                        <p class="text-sm">No upcoming reminders</p>
                    </div>
                @endforelse
                </div>
            </div>

            <div class="bg-white rounded-2xl p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-700 mb-3">Recent Transactions</h3>
                <div class="space-y-1">
                    @foreach($recentIncomes->take(3) as $inc)
                    <div class="flex items-center justify-between py-2.5 border-b border-slate-100">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-7 h-7 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                                <span class="text-emerald-600 text-xs font-bold">↑</span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-700 truncate">{{ \App\Models\Income::typeLabel($inc->type) }}</p>
                                <p class="text-xs text-slate-400">{{ $inc->date->format('d M') }}</p>
                            </div>
                        </div>
                        <span class="text-sm font-semibold text-emerald-600 flex-shrink-0">+₹{{ number_format($inc->amount,0) }}</span>
                    </div>
                    @endforeach
                    @foreach($recentExpenses->take(3) as $exp)
                    <div class="flex items-center justify-between py-2.5 border-b border-slate-100 last:border-0">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-7 h-7 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                                <span class="text-red-600 text-xs font-bold">↓</span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-700 truncate">{{ $exp->category->name ?? 'Unknown' }}</p>
                                <p class="text-xs text-slate-400">{{ $exp->date->format('d M') }}</p>
                            </div>
                        </div>
                        <span class="text-sm font-semibold text-red-500 flex-shrink-0">-₹{{ number_format($exp->amount,0) }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── Quick Actions ── --}}
        <div class="bg-white rounded-2xl p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">Quick Actions</h3>
            <div class="grid grid-cols-3 sm:flex sm:flex-wrap gap-2">
                <a href="{{ route('income.create') }}" class="quick-btn bg-emerald-50 text-emerald-700 hover:bg-emerald-100">+ Income</a>
                <a href="{{ route('expense.create') }}" class="quick-btn bg-red-50 text-red-700 hover:bg-red-100">+ Expense</a>
                <a href="{{ route('bank.create') }}" class="quick-btn bg-blue-50 text-blue-700 hover:bg-blue-100">+ Bank</a>
                <a href="{{ route('loan.create') }}" class="quick-btn bg-orange-50 text-orange-700 hover:bg-orange-100">+ Loan</a>
                <a href="{{ route('investment.create') }}" class="quick-btn bg-purple-50 text-purple-700 hover:bg-purple-100">+ Invest</a>
                <a href="{{ route('reminder.create') }}" class="quick-btn bg-amber-50 text-amber-700 hover:bg-amber-100">+ Reminder</a>
            </div>
        </div>

    </div>

    <style>
    .quick-btn{display:flex;align-items:center;justify-content:center;padding:10px 16px;border-radius:12px;font-size:12.5px;font-weight:600;transition:background .15s;text-decoration:none;text-align:center}
    </style>

    <script>
    new Chart(document.getElementById('incomeExpenseChart'),{
        type:'bar',
        data:{
            labels:@json($monthLabels->values()),
            datasets:[
                {label:'Income',data:@json($monthlyIncome->values()),backgroundColor:'#10b981',borderRadius:6,borderSkipped:false},
                {label:'Expense',data:@json($monthlyExpense->values()),backgroundColor:'#ef4444',borderRadius:6,borderSkipped:false}
            ]
        },
        options:{responsive:true,plugins:{legend:{position:'bottom',labels:{boxWidth:12,padding:12,font:{size:11}}}},scales:{y:{beginAtZero:true,ticks:{font:{size:10}},grid:{color:'#f1f5f9'}},x:{ticks:{font:{size:10}},grid:{display:false}}}}
    });
    const catData=@json($categoryExpenses->values());
    if(catData.length>0){
        new Chart(document.getElementById('categoryChart'),{
            type:'doughnut',
            data:{labels:catData.map(c=>c.name),datasets:[{data:catData.map(c=>c.total),backgroundColor:['#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#14b8a6','#f97316','#3b82f6'],borderWidth:0,hoverOffset:6}]},
            options:{responsive:true,cutout:'65%',plugins:{legend:{position:'bottom',labels:{boxWidth:10,padding:10,font:{size:10}}}}}
        });
    } else {
        document.getElementById('categoryChart').insertAdjacentHTML('afterend','<p class="text-sm text-slate-400 text-center py-6">No expenses this month</p>');
        document.getElementById('categoryChart').remove();
    }
    </script>
</x-app-layout>
