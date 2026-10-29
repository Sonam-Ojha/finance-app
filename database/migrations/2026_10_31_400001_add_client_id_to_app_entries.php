<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The id the app gives an entry it queued offline, the same on every
        // resend: a resend of one already saved is answered, not saved twice.
        // Null for everything else; NULLs never clash in a unique index.
        foreach (['app_expenses', 'app_incomes'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('client_id', 64)->nullable()->after('user_id');
                $table->unique(['user_id', 'client_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (['app_expenses', 'app_incomes'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropUnique(['user_id', 'client_id']);
                $table->dropColumn('client_id');
            });
        }
    }
};
