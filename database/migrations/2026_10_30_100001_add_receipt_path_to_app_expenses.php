<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A photo of an expense's receipt. The file is private — on the `receipts`
 * disk (storage/app/receipts/{user_id}/…), never under public/ — and is
 * served only to its owner (App\Support\Receipts). The column holds its path
 * on that disk, or NULL when there is none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_expenses', function (Blueprint $table) {
            $table->string('receipt_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_expenses', function (Blueprint $table) {
            $table->dropColumn('receipt_path');
        });
    }
};
