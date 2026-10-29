<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rent, EMIs, subscriptions, salary: an expense or income that repeats every
 * month, week or year. `recurring:post` adds the entry itself on its day
 * (App\Models\RecurringEntry), and the entry remembers which rule made it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 7);                          // expense, income
            $table->unsignedBigInteger('amount_minor');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete(); // expenses
            $table->string('source', 40)->nullable();          // incomes
            $table->string('note', 120)->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->string('frequency', 7);                    // monthly, weekly, yearly
            $table->unsignedTinyInteger('day');                // the day of the month it was set for (31 posts on 28 Feb)
            $table->date('next_on');                            // the next day it posts, India time
            $table->date('last_on')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['active', 'next_on']);
        });

        foreach (['app_expenses', 'app_incomes'] as $entries) {
            Schema::table($entries, function (Blueprint $table) {
                $table->foreignId('recurring_entry_id')->nullable()->constrained('recurring_entries')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['app_expenses', 'app_incomes'] as $entries) {
            Schema::table($entries, fn (Blueprint $table) => $table->dropConstrainedForeignId('recurring_entry_id'));
        }
        Schema::dropIfExists('recurring_entries');
    }
};
