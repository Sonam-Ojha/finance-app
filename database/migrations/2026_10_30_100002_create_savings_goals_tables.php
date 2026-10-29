<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Savings goals ("Goa trip ₹20,000") and the money put in or taken out of
 * them. saved_minor is the sum of a goal's contributions, kept in step by
 * App\Support\Goals — the one place contributions are written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('emoji', 16);
            $table->string('color', 7);
            $table->unsignedBigInteger('target_minor');
            $table->unsignedBigInteger('saved_minor')->default(0);
            $table->date('deadline')->nullable();
            // The first time it reached its target: the "reached" push goes out then, once.
            $table->timestamp('reached_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'archived_at']);
        });

        Schema::create('goal_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained('savings_goals')->cascadeOnDelete();
            // Positive adds money, negative withdraws it.
            $table->bigInteger('amount_minor');
            $table->string('note', 120)->nullable();
            // The day it happened. "on" in the API; not the column name, as ON is an SQL keyword.
            $table->date('on_date');
            $table->timestamps();
            $table->index(['goal_id', 'on_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_contributions');
        Schema::dropIfExists('savings_goals');
    }
};
