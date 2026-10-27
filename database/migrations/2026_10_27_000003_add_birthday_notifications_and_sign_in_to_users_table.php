<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('birthday')->nullable()->after('theme_mode');
            $table->boolean('email_notifications')->default(true)->after('birthday');
            $table->string('google_id')->nullable()->unique()->after('email_notifications');
            $table->string('apple_id')->nullable()->unique()->after('google_id');
            // An account made with Google or Apple has no password.
            $table->string('password')->nullable()->change();
            // Room for a currency the user added (codes of 2 to 5 letters).
            $table->string('currency', 5)->default('INR')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropUnique(['apple_id']);
            $table->dropColumn(['birthday', 'email_notifications', 'google_id', 'apple_id']);
        });
    }
};
