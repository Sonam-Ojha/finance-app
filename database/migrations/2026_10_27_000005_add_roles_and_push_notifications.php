<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // "user" or "admin". Admins see the Admin section of the web app.
            $table->string('role', 20)->default('user')->after('email');
        });

        // One row per phone (or browser) a user is signed in on: where a push goes.
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 512)->unique();
            $table->string('platform', 20)->default('android');
            $table->timestamps();
        });

        // Every notification an admin sent — the history in the web app, and
        // the inbox each user's app reads.
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 100);
            $table->string('body', 500);
            // "all", or "user" with target_user_id.
            $table->string('audience', 10);
            $table->foreignId('target_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            // sent / not_configured (saved to the inbox only, no push service yet)
            $table->string('status', 20);
            $table->unsignedInteger('devices')->default(0);
            $table->unsignedInteger('delivered')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->timestamps();
            $table->index(['audience', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
        Schema::dropIfExists('device_tokens');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
