<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // NULL is the whole-month budget; otherwise it overrides that
            // category's standing budget_minor for this month only.
            $table->foreignId('category_id')->nullable()->constrained('categories')->cascadeOnDelete();
            // category_id with NULL as 0, kept in step by the Budget model. A
            // unique index treats NULLs as distinct, so it could not stop two
            // whole-month budgets; and MySQL forbids a cascading foreign key on
            // the base column of a stored generated column.
            $table->unsignedBigInteger('category_key')->default(0);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();
            $table->unique(['user_id', 'category_key', 'year', 'month']);
            $table->index(['user_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
