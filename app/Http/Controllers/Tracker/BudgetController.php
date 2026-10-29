<?php

namespace App\Http\Controllers\Tracker;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tracker\Concerns\ReadsAmounts;
use App\Models\Budget;
use App\Models\Category;
use App\Support\ActivityMail;
use App\Support\DisplayCurrency;
use App\Support\Labels;
use App\Support\MonthBudgets;
use App\Support\Reports;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The app's two budget screens: one month's budgets (the whole month and
 * each category, overriding the every-month ones), and the every-month
 * budgets themselves.
 */
class BudgetController extends Controller
{
    use ReadsAmounts;

    public function index(Request $request)
    {
        $user   = $request->user();
        $money  = DisplayCurrency::for($user);
        $month  = Labels::clampMonth($request->query('month'));
        $totals = Reports::categoryTotals($user->id, $month)->keyBy('category_id');
        $budgets = MonthBudgets::load($user->id, $month);

        $rows = CategoryController::forUser($user->id)->map(function (Category $c) use ($money, $totals, $budgets) {
            $own   = $budgets->byCategory->get($c->id)?->amount_minor;
            $spent = $totals[$c->id]['total_minor'] ?? 0;
            $inForce = $own ?? $c->budget_minor;

            return [
                'category' => $c,
                'value'    => $own !== null ? $money->toInput($own) : '',
                'standing' => $c->budget_minor ? $money->format($c->budget_minor) : null,
                'spent'    => $spent,
                'usage'    => $inForce > 0 ? $money->budgetUsage($spent, $inForce) : null,
                'everyMonth' => $own === null && $inForce > 0,
            ];
        });

        $whole = $budgets->wholeMinor();
        $spentThisMonth = (int) $totals->sum('total_minor');

        return view('tracker.budgets', [
            'money'      => $money,
            'month'      => $month,
            'monthLabel' => Labels::month($month),
            'rows'       => $rows,
            'wholeValue' => $whole !== null ? $money->toInput($whole) : '',
            'wholeUsage' => $whole > 0 ? $money->budgetUsage($spentThisMonth, $whole) : null,
            'spentThisMonth' => $spentThisMonth,
        ]);
    }

    /** Saves the month's slots: an emptied one is deleted, a changed one updated, a new one created. */
    public function update(Request $request)
    {
        $user  = $request->user();
        $money = DisplayCurrency::for($user);
        $month = Labels::clampMonth($request->input('month'));
        [$year, $monthNumber] = array_map('intval', explode('-', $month));
        $budgets    = MonthBudgets::load($user->id, $month);
        $categories = CategoryController::forUser($user->id);

        // [category id or null, field name, what the error calls it, the budget there now]
        $slots = [[null, 'whole', 'The whole-month budget', $budgets->whole]];
        foreach ($categories as $c) $slots[] = [$c->id, "budgets.{$c->id}", $c->name, $budgets->byCategory->get($c->id)];

        $errors  = [];
        $changes = [];
        foreach ($slots as [$categoryId, $field, $name, $existing]) {
            $text = trim((string) $request->input($field));
            $value = $text === '' ? null : $this->amountValue($text, $money, $existing?->amount_minor);
            if ($text !== '' && $value === null) {
                $errors[$field] = "{$name} needs a number greater than zero, or an empty field.";
                continue;
            }
            if ($value !== $existing?->amount_minor) $changes[] = [$categoryId, $value, $existing];
        }
        if ($errors) throw ValidationException::withMessages($errors);

        $created = DB::transaction(function () use ($changes, $user, $year, $monthNumber) {
            $created = [];
            foreach ($changes as [$categoryId, $value, $existing]) {
                if ($value === null) {
                    $existing->delete();
                } elseif ($existing) {
                    $existing->update(['amount_minor' => $value]);
                } else {
                    $created[] = Budget::create([
                        'user_id' => $user->id, 'category_id' => $categoryId,
                        'year' => $year, 'month' => $monthNumber, 'amount_minor' => $value,
                    ]);
                }
            }
            return $created;
        });
        foreach ($created as $budget) ActivityMail::budgetSet($user, $budget);

        return redirect()->route('budgets.index', ['month' => $month])
            ->with('success', 'Budgets for ' . Labels::month($month) . ' saved');
    }

    public function defaults(Request $request)
    {
        $money = DisplayCurrency::for($request->user());

        return view('tracker.budget-defaults', [
            'money' => $money,
            'categories' => CategoryController::forUser($request->user()->id),
        ]);
    }

    public function updateDefaults(Request $request)
    {
        $user  = $request->user();
        $money = DisplayCurrency::for($user);
        $categories = CategoryController::forUser($user->id);

        $errors  = [];
        $changes = [];
        foreach ($categories as $c) {
            $text  = trim((string) $request->input("budgets.{$c->id}"));
            $value = $text === '' ? null : $this->amountValue($text, $money, $c->budget_minor);
            if ($text !== '' && $value === null) {
                $errors["budgets.{$c->id}"] = "{$c->name} needs a number greater than zero, or an empty field.";
                continue;
            }
            if ($value !== $c->budget_minor) $changes[$c->id] = $value;
        }
        if ($errors) throw ValidationException::withMessages($errors);

        DB::transaction(function () use ($changes, $user) {
            foreach ($changes as $id => $value) {
                Category::where('id', $id)->where('user_id', $user->id)->update(['budget_minor' => $value]);
            }
        });

        return redirect()->route('budgets.defaults')->with('success', 'Budgets saved');
    }
}
