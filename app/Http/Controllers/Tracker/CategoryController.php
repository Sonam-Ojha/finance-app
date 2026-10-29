<?php

namespace App\Http\Controllers\Tracker;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tracker\Concerns\ReadsAmounts;
use App\Models\Category;
use App\Support\ActivityMail;
use App\Support\DisplayCurrency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** The app's Categories screen and category form. Same rules as the API. */
class CategoryController extends Controller
{
    use ReadsAmounts;

    /** The app's icon picker (CategoryEmoji). */
    public const ICONS = ['🍔', '🛒', '🚗', '🛍️', '💡', '💊', '🎬', '✨', '☕', '🏠', '✈️', '📚', '🎁', '💪', '🐾', '📱', '⛽', '🎓', '💇', '🍿', '🧾', '💸', '🏦', '🎮', '💧', '🔑', '🛡️', '📶', '🔔', '🌴', '📣', '🧹'];

    /** The app's colour picker (CategoryPalette). */
    public const COLORS = ['#DA2020', '#EF661A', '#FCBD4C', '#34A866', '#228085', '#199CDA', '#283897', '#7E8ACC', '#915FC8', '#DA558C', '#B28B4E', '#737373'];

    public function index(Request $request)
    {
        return view('tracker.categories', [
            'money'      => DisplayCurrency::for($request->user()),
            'categories' => self::forUser($request->user()->id),
        ]);
    }

    public function create(Request $request)
    {
        return $this->form($request, null);
    }

    public function edit(Request $request, Category $category)
    {
        $this->authorizeOwner($request, $category);
        return $this->form($request, $category);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $this->validated($request, null);
        $sortOrder = Category::where('user_id', $user->id)->max('sort_order') ?? -1;

        $category = Category::create($data + ['user_id' => $user->id, 'sort_order' => $sortOrder + 1]);
        ActivityMail::categoryAdded($user, $category);

        // Made from the expense form: go back to it with the new one picked.
        $to = $request->input('for') === 'expense'
            ? route('expenses.create', ['category_id' => $category->id])
            : route('categories.index');

        return redirect($to)->with('success', "Category “{$category->name}” created");
    }

    public function update(Request $request, Category $category)
    {
        $this->authorizeOwner($request, $category);
        $category->update($this->validated($request, $category));

        return redirect()->route('categories.index')->with('success', "Category “{$category->name}” updated");
    }

    public function destroy(Request $request, Category $category)
    {
        $this->authorizeOwner($request, $category);
        $fallback = self::fallbackFor($category);
        if (! $fallback) {
            return back()->with('error', 'You need at least one category — create another before deleting this one.');
        }

        // Its expenses are refiled, never removed.
        DB::transaction(function () use ($category, $fallback) {
            $category->appExpenses()->update(['category_id' => $fallback->id]);
            $category->delete();
        });

        return redirect()->route('categories.index')->with('success', "Category “{$category->name}” deleted");
    }

    public static function forUser(int $uid)
    {
        return Category::where('user_id', $uid)->orderBy('sort_order')->orderBy('name')->get();
    }

    /** Where a deleted category's expenses go: "Other", else the first other category. */
    public static function fallbackFor(Category $category): ?Category
    {
        $others = self::forUser($category->user_id)->where('id', '!=', $category->id);

        return $others->first(fn ($c) => strcasecmp($c->name, 'Other') === 0) ?? $others->first();
    }

    private function form(Request $request, ?Category $category)
    {
        $money    = DisplayCurrency::for($request->user());
        $fallback = $category ? self::fallbackFor($category) : null;
        $count    = $category ? $category->appExpenses()->count() : 0;

        return view('tracker.category-form', [
            'money'    => $money,
            'category' => $category,
            'icons'    => self::ICONS,
            'colors'   => self::COLORS,
            'for'      => $request->query('for'),
            'fallback' => $fallback,
            'deleteMessage' => ! $fallback ? null : ($count > 0
                ? "Its {$count} " . ($count === 1 ? 'expense' : 'expenses') . " will be moved to “{$fallback->name}”. Nothing is lost."
                : 'It has no expenses, so nothing else changes.'),
        ]);
    }

    private function validated(Request $request, ?Category $category): array
    {
        $uid   = $request->user()->id;
        $money = DisplayCurrency::for($request->user());
        $request->merge(['name' => trim((string) $request->name)]);

        $request->validate([
            'name'   => ['required', 'max:40', Rule::unique('categories')->where('user_id', $uid)->ignore($category?->id)],
            'icon'   => 'required|string|max:16',
            'color'  => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'budget' => ['nullable', $this->amountRule($money, $category?->budget_minor, 'Budget must be a number greater than zero, or left empty.', true)],
        ], [
            'name.required' => 'Give the category a name.',
            'name.max'      => 'Keep the name to 40 characters.',
            'name.unique'   => 'A category with that name already exists.',
            'icon.*'        => 'Pick an icon.',
            'color.*'       => 'Pick a colour.',
        ]);

        return [
            'name'         => $request->name,
            'icon'         => $request->icon,
            'color'        => strtoupper($request->color),
            'budget_minor' => $this->amountValue($request->budget, $money, $category?->budget_minor),
        ];
    }

    private function authorizeOwner(Request $request, Category $category): void
    {
        if ($category->user_id !== $request->user()->id) abort(404);
    }
}
