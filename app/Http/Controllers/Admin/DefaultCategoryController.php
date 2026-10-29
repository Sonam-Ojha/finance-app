<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tracker\CategoryController;
use App\Models\DefaultCategory;
use App\Support\AdminActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin › Default categories: the list every new account starts with
 * (App\Support\DefaultCategories::seed). Existing accounts never change.
 */
class DefaultCategoryController extends Controller
{
    private const BUDGET_MESSAGE = 'Enter the monthly budget in rupees, like 5000, or leave it empty.';

    public function index()
    {
        return $this->page(null);
    }

    public function edit(DefaultCategory $defaultCategory)
    {
        return $this->page($defaultCategory);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $category = DefaultCategory::create($data + ['sort_order' => (DefaultCategory::max('sort_order') ?? -1) + 1]);
        AdminActivity::record('default_category.added', "added the default category “{$category->name}”", $category);

        return redirect()->route('admin.default-categories.index')
            ->with('success', "“{$category->name}” added. New accounts start with it from now on.");
    }

    public function update(Request $request, DefaultCategory $defaultCategory)
    {
        $before = $defaultCategory->name;
        $defaultCategory->update($this->validated($request, $defaultCategory));
        AdminActivity::record('default_category.updated', $before === $defaultCategory->name
            ? "updated the default category “{$defaultCategory->name}”"
            : "renamed the default category “{$before}” to “{$defaultCategory->name}”", $defaultCategory);

        return redirect()->route('admin.default-categories.index')
            ->with('success', "“{$defaultCategory->name}” saved. New accounts get it like this from now on.");
    }

    /** One place up or down the list. */
    public function move(Request $request, DefaultCategory $defaultCategory)
    {
        $request->validate(['direction' => 'required|in:up,down']);
        $up = $request->input('direction') === 'up';

        $list = DefaultCategory::ordered()->get()->values();
        $index = $list->search(fn ($c) => $c->is($defaultCategory));
        $swap = $up ? $index - 1 : $index + 1;
        if ($index === false || $swap < 0 || $swap >= $list->count()) {
            return redirect()->route('admin.default-categories.index');
        }

        // Renumber the whole list, so gaps or ties left by older data never stick.
        $ordered = $list->all();
        [$ordered[$index], $ordered[$swap]] = [$ordered[$swap], $ordered[$index]];
        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $position => $category) {
                if ($category->sort_order !== $position) $category->update(['sort_order' => $position]);
            }
        });
        AdminActivity::record('default_category.moved',
            'moved the default category “' . $defaultCategory->name . '” ' . ($up ? 'up' : 'down') . ' to place ' . ($swap + 1), $defaultCategory);

        return redirect()->route('admin.default-categories.index');
    }

    public function destroy(DefaultCategory $defaultCategory)
    {
        if (DefaultCategory::count() <= 1) {
            return back()->with('error', 'Keep at least one category, so new accounts have somewhere to file expenses.');
        }

        $defaultCategory->delete();
        AdminActivity::record('default_category.deleted', "deleted the default category “{$defaultCategory->name}”", $defaultCategory);

        return redirect()->route('admin.default-categories.index')
            ->with('success', "“{$defaultCategory->name}” removed. Accounts that already have it keep it.");
    }

    private function page(?DefaultCategory $editing)
    {
        return view('admin.default-categories.index', [
            'categories' => DefaultCategory::ordered()->get(),
            'editing'    => $editing,
            'icons'      => CategoryController::ICONS,
            'colors'     => CategoryController::COLORS,
        ]);
    }

    private function validated(Request $request, ?DefaultCategory $category): array
    {
        $request->merge([
            'name'   => trim((string) $request->input('name')),
            'icon'   => trim((string) $request->input('icon')),
            'budget' => str_replace([',', ' ', '₹'], '', (string) $request->input('budget')),
        ]);

        $request->validate([
            'name'   => ['required', 'max:40', Rule::unique('default_categories')->ignore($category?->id)],
            'icon'   => 'required|string|max:16',
            'color'  => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'budget' => ['bail', 'nullable', 'regex:/^\d{1,10}(\.\d{1,2})?$/',
                fn ($attribute, $value, $fail) => self::toMinor($value) > 0 || $fail(self::BUDGET_MESSAGE)],
        ], [
            'name.required' => 'Give the category a name.',
            'name.max'      => 'Keep the name to 40 characters.',
            'name.unique'   => 'There is already a default category with that name.',
            'icon.*'        => 'Pick an emoji, or type one.',
            'color.*'       => 'Pick a colour, or type one like #2DD4BF.',
            'budget.*'      => self::BUDGET_MESSAGE,
        ]);

        $budget = $request->input('budget');

        return [
            'name'         => $request->input('name'),
            'icon'         => $request->input('icon'),
            'color'        => strtoupper($request->input('color')),
            'budget_minor' => $budget === null || $budget === '' ? null : self::toMinor($budget),
        ];
    }

    /** "5000.5" rupees as 500050 hundredths, without floats. */
    private static function toMinor(string $rupees): int
    {
        [$whole, $fraction] = array_pad(explode('.', $rupees, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
