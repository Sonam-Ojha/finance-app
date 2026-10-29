<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            // The sign-in (Sanctum token) the phone registered under. When it
            // ends — sign-out, expiry, a password reset, a block — the phone
            // stops getting that account's pushes. Null for phones registered
            // before this column; the app registers again on its next start.
            $table->foreignId('access_token_id')->nullable()->after('user_id')
                ->constrained('personal_access_tokens')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('access_token_id');
        });
    }
};
