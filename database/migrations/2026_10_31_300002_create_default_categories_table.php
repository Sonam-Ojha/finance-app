<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The categories every new account starts with, edited in Admin › Default
 * categories. App\Support\DefaultCategories::seed() copies them into a new
 * user's `categories`; existing accounts never change.
 *
 * Starts with the eight that were hardcoded in DefaultCategories until now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('default_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->string('icon', 16);
            $table->string('color', 7);
            // Hundredths of a rupee, like categories.budget_minor; null = no budget.
            $table->unsignedBigInteger('budget_minor')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        $rows = [
            ['Food & Drink',      '🍔', '#FF6B6B'],
            ['Groceries',         '🛒', '#2DD4BF'],
            ['Transport',         '🚗', '#38BDF8'],
            ['Shopping',          '🛍', '#A78BFA'],
            ['Bills & Utilities', '💡', '#FBBF24'],
            ['Health',            '💊', '#34D399'],
            ['Entertainment',     '🎬', '#F472B6'],
            ['Other',             '✨', '#94A3B8'],
        ];
        DB::table('default_categories')->insert(array_map(fn ($row, $i) => [
            'name' => $row[0], 'icon' => $row[1], 'color' => $row[2], 'budget_minor' => null,
            'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now,
        ], $rows, array_keys($rows)));
    }

    public function down(): void
    {
        Schema::dropIfExists('default_categories');
    }
};
