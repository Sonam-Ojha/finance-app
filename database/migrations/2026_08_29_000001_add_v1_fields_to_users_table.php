<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin')->nullable()->after('password');
            $table->boolean('onboarded')->default(false)->after('pin');
            $table->string('currency', 3)->default('INR')->after('onboarded');
            $table->string('theme_mode', 10)->default('light')->after('currency');
            $table->timestamp('import_completed_at')->nullable()->after('theme_mode');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pin', 'onboarded', 'currency', 'theme_mode', 'import_completed_at']);
        });
    }
};
