<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** An admin notification can go out by push, by email, or both. Earlier ones were push only. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_notifications', function (Blueprint $table) {
            $table->boolean('via_push')->default(true)->after('target_user_id');
            $table->boolean('via_email')->default(false)->after('via_push');
            $table->unsignedInteger('email_recipients')->default(0)->after('failed');
            $table->unsignedInteger('emails_sent')->default(0)->after('email_recipients');
            $table->unsignedInteger('emails_failed')->default(0)->after('emails_sent');
        });
    }

    public function down(): void
    {
        Schema::table('admin_notifications', function (Blueprint $table) {
            $table->dropColumn(['via_push', 'via_email', 'email_recipients', 'emails_sent', 'emails_failed']);
        });
    }
};
