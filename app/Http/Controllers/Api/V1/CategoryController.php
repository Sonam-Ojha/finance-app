<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $cats = Category::where('user_id', $request->user()->id)
            ->orderBy('sort_order')->orderBy('name')->get();
        return response()->json(['data' => $cats]);
    }

    public function store(Request $request)
    {
        $uid = $request->user()->id;
        $request->validate([
            'name'         => ['required', 'max:40', Rule::unique('categories')->where('user_id', $uid)],
            'icon'         => 'required|string|max:16',
            'color'        => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'budget_minor' => 'nullable|integer|min:1',
        ], [
            'name.required' => 'Give the category a name.',
            'name.unique'   => 'A category with that name already exists.',
            'budget_minor.min' => 'Budget must be a number greater than zero, or left empty.',
        ]);

        $sortOrder = Category::where('user_id', $uid)->max('sort_order') ?? -1;

        try {
            $category = Category::create([
                'user_id'      => $uid,
                'name'         => trim($request->name),
                'icon'         => $request->icon,
                'color'        => $request->color,
                'budget_minor' => $request->budget_minor,
                'sort_order'   => $sortOrder + 1,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json(['message' => 'A category with that name already exists.', 'errors' => ['name' => ['A category with that name already exists.']]], 422);
            }
            throw $e;
        }

        return response()->json(['data' => $category], 201);
    }

    public function show(Request $request, Category $category)
    {
        $this->authorizeOwner($category, $request->user());
        $expenseCount = $category->appExpenses()->count();
        return response()->json(['data' => array_merge($category->toArray(), ['expense_count' => $expenseCount])]);
    }

    public function update(Request $request, Category $category)
    {
        $this->authorizeOwner($category, $request->user());
        $uid = $request->user()->id;
        $request->validate([
            'name'         => ['required', 'max:40', Rule::unique('categories')->where('user_id', $uid)->ignore($category->id)],
            'icon'         => 'required|string|max:16',
            'color'        => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'budget_minor' => 'nullable|integer|min:1',
        ], [
            'name.required' => 'Give the category a name.',
            'name.unique'   => 'A category with that name already exists.',
            'budget_minor.min' => 'Budget must be a number greater than zero, or left empty.',
        ]);

        try {
            $category->update([
                'name'         => trim($request->name),
                'icon'         => $request->icon,
                'color'        => $request->color,
                'budget_minor' => $request->budget_minor,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json(['message' => 'A category with that name already exists.', 'errors' => ['name' => ['A category with that name already exists.']]], 422);
            }
            throw $e;
        }

        return response()->json(['data' => $category->fresh()]);
    }

    public function destroy(Request $request, Category $category)
    {
        $this->authorizeOwner($category, $request->user());
        $uid = $request->user()->id;

        $totalCats = Category::where('user_id', $uid)->count();
        if ($totalCats <= 1) {
            return response()->json(['message' => 'You need at least one category — create another before deleting this one.'], 422);
        }

        $expenseCount = $category->appExpenses()->count();
        $moveTo = $request->query('move_to');

        if ($expenseCount > 0 && ! $moveTo) {
            return response()->json([
                'message'       => 'This category has expenses. Provide move_to to refile them.',
                'code'          => 'category_in_use',
                'expense_count' => $expenseCount,
            ], 409);
        }

        DB::transaction(function () use ($category, $moveTo, $uid) {
            if ($moveTo) {
                $target = Category::where('id', $moveTo)->where('user_id', $uid)->firstOrFail();
                $category->appExpenses()->update(['category_id' => $target->id]);
            }
            $category->delete();
        });

        return response()->noContent();
    }

    public function updateBudget(Request $request, Category $category)
    {
        $this->authorizeOwner($category, $request->user());
        $request->validate([
            'budget_minor' => 'nullable|integer|min:1',
        ], [
            'budget_minor.min' => 'Budget must be a number greater than zero, or left empty.',
        ]);
        $category->update(['budget_minor' => $request->budget_minor]);
        return response()->json(['data' => $category->fresh()]);
    }

    public function bulkUpdateBudgets(Request $request)
    {
        $uid = $request->user()->id;
        $request->validate([
            'budgets'                => 'required|array',
            'budgets.*.id'           => 'required|integer',
            'budgets.*.budget_minor' => 'nullable|integer|min:1',
        ]);

        DB::transaction(function () use ($request, $uid) {
            foreach ($request->budgets as $item) {
                Category::where('id', $item['id'])->where('user_id', $uid)
                    ->update(['budget_minor' => $item['budget_minor']]);
            }
        });

        $cats = Category::where('user_id', $uid)->orderBy('sort_order')->orderBy('name')->get();
        return response()->json(['data' => $cats]);
    }

    private function authorizeOwner(Category $category, $user)
    {
        if ($category->user_id !== $user->id) abort(404, 'Not found.');
    }
}
