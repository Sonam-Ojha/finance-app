<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('investment_name');
            $table->string('category'); // lic, mutual_fund, stocks, fd, gold, property, other
            $table->date('date');
            $table->decimal('amount_invested', 12, 2);
            $table->decimal('current_value', 12, 2)->nullable();
            $table->date('maturity_date')->nullable();
            $table->decimal('returns', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
