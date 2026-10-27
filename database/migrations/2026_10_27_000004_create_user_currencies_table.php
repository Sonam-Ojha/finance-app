<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A row for a built-in code holds just its rate; any other code is a
        // currency the user added, with its own label and symbol.
        Schema::create('user_currencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 5);
            $table->string('label', 30)->nullable();
            $table->string('symbol', 4)->nullable();
            // Rupees for one unit.
            $table->decimal('rate', 15, 6);
            $table->timestamps();
            $table->unique(['user_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_currencies');
    }
};
