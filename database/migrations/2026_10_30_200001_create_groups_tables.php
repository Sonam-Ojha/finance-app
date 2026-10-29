<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Groups: shared expenses, Splitwise-style (App\Support\Groups and
 * App\Support\GroupLedger). A space of its own — nothing here touches a
 * user's personal app_expenses.
 *
 * The ledger's user columns (paid_by, a share's user_id, a settlement's
 * from/to, created_by) deliberately have no foreign key: a member who
 * deletes their account must not take the expenses others shared with them
 * along (their balance would vanish from everyone else's), nor be blocked
 * from deleting. Their rows stay and read as "Former member".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);
            $table->string('emoji', 16)->default('👥');
            // Null once the creator's account is gone; any member may then delete it.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->unique(['group_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('group_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->string('description', 80);
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('paid_by');
            $table->date('spent_on');
            $table->string('split', 8); // equal | exact
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['group_id', 'spent_on']);
            $table->index('paid_by');
        });

        // Always sum exactly to the expense's amount_minor.
        Schema::create('group_expense_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_expense_id')->constrained('group_expenses')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('amount_minor');
            $table->unique(['group_expense_id', 'user_id']);
            $table->index('user_id');
        });

        // "From paid To": the from side's balance goes up, the to side's down.
        Schema::create('group_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->unsignedBigInteger('from_user_id');
            $table->unsignedBigInteger('to_user_id');
            $table->unsignedBigInteger('amount_minor');
            // `on` in the API; a reserved word as a column name.
            $table->date('settled_on');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['group_id', 'settled_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_settlements');
        Schema::dropIfExists('group_expense_shares');
        Schema::dropIfExists('group_expenses');
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('groups');
    }
};
