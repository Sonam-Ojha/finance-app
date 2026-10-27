<?php

namespace App\Support;

use App\Models\Category;
use App\Models\User;

/** The eight categories every new account starts with, however it signed up. */
class DefaultCategories
{
    private const CATEGORIES = [
        ['name' => 'Food & Drink',      'icon' => '🍔', 'color' => '#FF6B6B'],
        ['name' => 'Groceries',         'icon' => '🛒', 'color' => '#2DD4BF'],
        ['name' => 'Transport',         'icon' => '🚗', 'color' => '#38BDF8'],
        ['name' => 'Shopping',          'icon' => '🛍', 'color' => '#A78BFA'],
        ['name' => 'Bills & Utilities', 'icon' => '💡', 'color' => '#FBBF24'],
        ['name' => 'Health',            'icon' => '💊', 'color' => '#34D399'],
        ['name' => 'Entertainment',     'icon' => '🎬', 'color' => '#F472B6'],
        ['name' => 'Other',             'icon' => '✨', 'color' => '#94A3B8'],
    ];

    public static function seed(User $user): void
    {
        foreach (self::CATEGORIES as $i => $cat) {
            Category::create([
                'user_id'    => $user->id,
                'name'       => $cat['name'],
                'icon'       => $cat['icon'],
                'color'      => $cat['color'],
                'sort_order' => $i,
            ]);
        }
    }
}
