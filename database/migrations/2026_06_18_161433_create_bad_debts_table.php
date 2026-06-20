<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bad_debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('person_name');
            $table->string('mobile_number')->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('date_given');
            $table->string('reason')->nullable();
            $table->date('expected_return_date')->nullable();
            $table->string('status')->default('pending'); // pending, received, partial_received
            $table->decimal('received_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bad_debts');
    }
};
