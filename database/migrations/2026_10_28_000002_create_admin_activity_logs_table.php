<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Every action an admin takes in the admin pages, newest read first. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_activity_logs', function (Blueprint $table) {
            $table->id();
            // Kept when the admin's account goes, so the log stays whole.
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            // e.g. user.role_changed, notification.sent
            $table->string('action', 50);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description', 255);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activity_logs');
    }
};
