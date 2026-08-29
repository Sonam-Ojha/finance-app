<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppExpense;
use App\Models\AppIncome;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImportController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->import_completed_at) {
            return response()->json(['message' => 'Import already completed.', 'code' => 'already_imported'], 409);
        }

        $request->validate([
            'categories'                  => 'required|array',
            'categories.*.local_id'       => 'required|integer',
            'categories.*.name'           => 'required|string|max:40',
            'categories.*.icon'           => 'required|string|max:16',
            'categories.*.color'          => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'categories.*.budget_minor'   => 'nullable|integer|min:1',
            'categories.*.sort_order'     => 'nullable|integer',
            'expenses'                    => 'nullable|array',
            'expenses.*.amount_minor'     => 'required|integer|min:1',
            'expenses.*.local_category_id'=> 'required|integer',
            'expenses.*.note'             => 'nullable|string|max:120',
            'expenses.*.spent_at'         => 'required|date_format:Y-m-d',
            'incomes'                     => 'nullable|array',
            'incomes.*.amount_minor'      => 'required|integer|min:1',
            'incomes.*.source'            => 'required|string|max:40',
            'incomes.*.note'              => 'nullable|string|max:120',
            'incomes.*.received_at'       => 'required|date_format:Y-m-d',
        ]);

        $localIdSet = collect($request->categories)->pluck('local_id')->flip();
        foreach ($request->expenses ?? [] as $exp) {
            if (! $localIdSet->has($exp['local_category_id'])) {
                return response()->json(['message' => 'Expense references an unknown local_category_id: ' . $exp['local_category_id']], 422);
            }
        }

        $result = DB::transaction(function () use ($request, $user) {
            $uid = $user->id;
            $categoryMap = [];
            $created = 0;
            $merged  = 0;

            foreach ($request->categories as $cat) {
                $existing = Category::where('user_id', $uid)
                    ->whereRaw('LOWER(name) = ?', [strtolower(trim($cat['name']))])
                    ->first();

                if ($existing) {
                    $categoryMap[$cat['local_id']] = $existing->id;
                    $merged++;
                } else {
                    $maxOrder = Category::where('user_id', $uid)->max('sort_order') ?? -1;
                    $newCat = Category::create([
                        'user_id'      => $uid,
                        'name'         => trim($cat['name']),
                        'icon'         => $cat['icon'],
                        'color'        => $cat['color'],
                        'budget_minor' => $cat['budget_minor'] ?? null,
                        'sort_order'   => $maxOrder + 1,
                    ]);
                    $categoryMap[$cat['local_id']] = $newCat->id;
                    $created++;
                }
            }

            $expCount = 0;
            foreach ($request->expenses ?? [] as $exp) {
                AppExpense::create([
                    'user_id'      => $uid,
                    'amount_minor' => $exp['amount_minor'],
                    'category_id'  => $categoryMap[$exp['local_category_id']],
                    'note'         => isset($exp['note']) ? (trim($exp['note']) ?: null) : null,
                    'spent_at'     => $exp['spent_at'],
                ]);
                $expCount++;
            }

            $incCount = 0;
            foreach ($request->incomes ?? [] as $inc) {
                AppIncome::create([
                    'user_id'      => $uid,
                    'amount_minor' => $inc['amount_minor'],
                    'source'       => trim($inc['source']),
                    'note'         => isset($inc['note']) ? (trim($inc['note']) ?: null) : null,
                    'received_at'  => $inc['received_at'],
                ]);
                $incCount++;
            }

            $user->update(['import_completed_at' => now()]);

            return [
                'category_map' => array_map('strval', array_flip($categoryMap)) + $categoryMap,
                'imported' => [
                    'categories_created' => $created,
                    'categories_merged'  => $merged,
                    'expenses'           => $expCount,
                    'incomes'            => $incCount,
                ],
            ];
        });

        $catMap = [];
        foreach ($request->categories as $cat) {
            // Return map as local_id (string) => server_id
        }

        return response()->json(['data' => $result], 201);
    }
}
