<?php

namespace App\Support;

use App\Models\Category;
use App\Models\DefaultCategory;
use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * The categories every new account starts with, however it signed up: the
 * `default_categories` table, edited in Admin › Default categories. Changes
 * there reach new sign-ups only; nobody's existing categories change.
 */
class DefaultCategories
{
    /** Used only while the table is empty (or not migrated yet). */
    public const FALLBACK = [
        ['name' => 'Food & Drink',      'icon' => '🍔', 'color' => '#FF6B6B'],
        ['name' => 'Groceries',         'icon' => '🛒', 'color' => '#2DD4BF'],
        ['name' => 'Transport',         'icon' => '🚗', 'color' => '#38BDF8'],
        ['name' => 'Shopping',          'icon' => '🛍', 'color' => '#A78BFA'],
        ['name' => 'Bills & Utilities', 'icon' => '💡', 'color' => '#FBBF24'],
        ['name' => 'Health',            'icon' => '💊', 'color' => '#34D399'],
        ['name' => 'Entertainment',     'icon' => '🎬', 'color' => '#F472B6'],
        ['name' => 'Other',             'icon' => '✨', 'color' => '#94A3B8'],
    ];

    /** @return list<array{name: string, icon: string, color: string, budget_minor: ?int}> */
    public static function list(): array
    {
        try {
            $rows = DefaultCategory::ordered()->get(['name', 'icon', 'color', 'budget_minor'])
                ->map(fn (DefaultCategory $c) => [
                    'name' => $c->name, 'icon' => $c->icon, 'color' => $c->color, 'budget_minor' => $c->budget_minor,
                ])->all();
        } catch (QueryException) {
            $rows = [];
        }

        return $rows ?: array_map(fn ($c) => $c + ['budget_minor' => null], self::FALLBACK);
    }

    public static function seed(User $user): void
    {
        foreach (self::list() as $i => $cat) {
            Category::create([
                'user_id'      => $user->id,
                'name'         => $cat['name'],
                'icon'         => $cat['icon'],
                'color'        => $cat['color'],
                'budget_minor' => $cat['budget_minor'],
                'sort_order'   => $i,
            ]);
        }
    }
}
