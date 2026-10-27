<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // NULL means "not recorded" — every row from before this column.
        Schema::table('app_expenses', function (Blueprint $table) {
            $table->string('payment_method', 20)->nullable()->after('note');
        });
        Schema::table('app_incomes', function (Blueprint $table) {
            $table->string('payment_method', 20)->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('app_expenses', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
        Schema::table('app_incomes', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
