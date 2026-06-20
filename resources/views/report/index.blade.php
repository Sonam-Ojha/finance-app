<x-app-layout>
    <x-slot name="title">Reports</x-slot>
    <div class="pt-2 max-w-2xl space-y-4">
        <h2 class="text-xl font-bold text-gray-800">Generate Report</h2>

        <div class="bg-white rounded-xl shadow p-6">
            <form method="POST" action="{{ route('report.generate') }}">
                @csrf
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Report Type *</label>
                        <select name="type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                            <option value="income">Income Report</option>
                            <option value="expense">Expense Report</option>
                            <option value="profit_loss">Profit / Loss Report</option>
                            <option value="investment">Investment Report</option>
                            <option value="loan">Loan Report</option>
                            <option value="commission">Commission Report</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Month (Optional)</label>
                        <select name="month" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">All Months</option>
                            @foreach(range(1,12) as $m)
                                <option value="{{ $m }}" @selected(request('month')==$m)>{{ date('F', mktime(0,0,0,$m,1)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Year *</label>
                        <select name="year" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                            @foreach(range(date('Y'), date('Y')-5) as $y)
                                <option value="{{ $y }}">{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Format</label>
                        <select name="format" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="view">View on Screen</option>
                            <option value="pdf">Download PDF</option>
                        </select>
                    </div>
                </div>
                <div class="pt-4">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-blue-700">Generate Report</button>
                </div>
            </form>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
            @foreach([['income','Income','emerald'],['expense','Expense','red'],['profit_loss','Profit/Loss','purple'],['investment','Investment','indigo'],['loan','Loan','orange'],['commission','Commission','teal']] as [$type, $label, $color])
                <form method="POST" action="{{ route('report.generate') }}">
                    @csrf
                    <input type="hidden" name="type" value="{{ $type }}">
                    <input type="hidden" name="year" value="{{ date('Y') }}">
                    <input type="hidden" name="month" value="{{ date('n') }}">
                    <button class="w-full bg-{{ $color }}-100 text-{{ $color }}-700 py-3 rounded-xl text-sm font-medium hover:bg-{{ $color }}-200 transition">
                        📊 {{ $label }} Report<br><span class="text-xs font-normal opacity-70">{{ date('M Y') }}</span>
                    </button>
                </form>
            @endforeach
        </div>
    </div>
</x-app-layout>
