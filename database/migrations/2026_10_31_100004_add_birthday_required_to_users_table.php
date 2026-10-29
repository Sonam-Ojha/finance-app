<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // True for an account Google or Apple sign-in just made: it has no
            // date of birth yet. The website asks for one before anything
            // else; the app gates on `birthday_required` in the user payload.
            // Saving a birthday clears it (App\Models\User::booted). Existing
            // accounts are never asked.
            $table->boolean('birthday_required')->default(false)->after('birthday');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('birthday_required');
        });
    }
};
