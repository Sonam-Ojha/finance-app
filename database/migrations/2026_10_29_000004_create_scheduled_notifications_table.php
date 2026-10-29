<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications an admin set to go out later — once, every day or every week
 * (App\Models\ScheduledNotification). `notifications:send-scheduled` sends
 * each one when `next_run_at` comes, as an ordinary admin notification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 100);
            $table->text('body');
            $table->string('audience', 10);
            $table->foreignId('target_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->boolean('via_push')->default(true);
            $table->boolean('via_email')->default(false);
            $table->string('repeat', 10);           // once, daily, weekly
            $table->string('time', 5);              // "19:00", India time
            $table->unsignedTinyInteger('weekday')->nullable(); // 0 = Sunday, for weekly
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('runs')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['active', 'next_run_at']);
        });

        Schema::table('admin_notifications', function (Blueprint $table) {
            $table->foreignId('scheduled_notification_id')->nullable()->after('target_user_id')
                ->constrained('scheduled_notifications')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('admin_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scheduled_notification_id');
        });
        Schema::dropIfExists('scheduled_notifications');
    }
};
