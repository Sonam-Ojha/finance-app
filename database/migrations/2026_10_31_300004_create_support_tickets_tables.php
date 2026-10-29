<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Help & support: a user's requests (from the website or the app) and the
 * conversation on each — their messages and the replies from Admin › Support.
 * Written only through App\Support\SupportTickets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject', 120);
            // open: waiting for support · answered: support replied last · closed
            $table->string('status', 10)->default('open');
            // Who wrote last: user | support
            $table->string('last_reply_from', 10)->nullable();
            // A support reply the user has not opened yet.
            $table->boolean('unread_by_user')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'updated_at']);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            // user | support ("from" in the API; not the column name, as FROM is an SQL keyword)
            $table->string('sender', 10);
            // Who wrote it: the ticket's user, or the admin who replied.
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            // On the private `support` disk; only the ticket's user and admins can read it.
            $table->string('screenshot_path')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
    }
};
