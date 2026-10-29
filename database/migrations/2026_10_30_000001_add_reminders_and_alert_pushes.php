<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reminders the server sends on its own: the evening "log today's
 * spending" push, budget alerts as pushes, the birthday wish and the
 * monthly summary email — each user's switches, and what already went
 * out so nothing is sent twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // India time, "21:00"; null turns the daily reminder off.
            $table->string('daily_reminder_at', 5)->nullable()->default('21:00')->after('email_notifications');
            $table->boolean('budget_alert_push')->default(true)->after('daily_reminder_at');
            $table->boolean('monthly_summary')->default(true)->after('budget_alert_push');
            $table->date('last_reminded_on')->nullable()->after('monthly_summary');
            $table->unsignedSmallInteger('birthday_wished_year')->nullable()->after('last_reminded_on');
            $table->string('summary_sent_for', 7)->nullable()->after('birthday_wished_year'); // "2026-10"
        });

        // One row per budget alert pushed: "category:12" or "month", in a month, at 80 or 100 (%).
        Schema::create('budget_alert_pushes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 30);
            $table->string('month', 7);
            $table->unsignedTinyInteger('level');
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'scope', 'month', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_alert_pushes');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['daily_reminder_at', 'budget_alert_push', 'monthly_summary', 'last_reminded_on', 'birthday_wished_year', 'summary_sent_for']);
        });
    }
};
