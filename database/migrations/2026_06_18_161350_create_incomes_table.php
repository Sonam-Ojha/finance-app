<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // salary, lic_commission, business, received_from, other
            $table->date('date');
            $table->decimal('amount', 12, 2);
            $table->string('payment_mode')->nullable(); // cash, bank, upi, cheque
            $table->string('note')->nullable();
            // Salary fields
            $table->string('company_name')->nullable();
            $table->string('salary_month')->nullable();
            // LIC Commission fields
            $table->string('client_name')->nullable();
            $table->string('policy_number')->nullable();
            $table->string('plan_name')->nullable();
            $table->string('commission_type')->nullable();
            // Business fields
            $table->string('business_name')->nullable();
            // Received from person fields
            $table->string('person_name')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('reason')->nullable();
            // Other income fields
            $table->string('category_name')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incomes');
    }
};
