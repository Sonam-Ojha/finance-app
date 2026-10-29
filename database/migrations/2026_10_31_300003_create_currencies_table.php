<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The currencies the app and the website offer, edited in Admin › Currencies.
 * App\Support\Currencies reads the enabled ones (GET /api/v1/currencies).
 * Amounts are always stored in INR, which cannot be switched off.
 *
 * Starts with the nine that were hardcoded in Currencies::BUILT_IN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 5)->unique();
            $table->string('name', 40);
            $table->string('symbol', 8);
            $table->unsignedTinyInteger('decimals')->default(2);
            // Rupees for one unit: offered to people who pick it before they set their own.
            $table->decimal('default_rate', 18, 6)->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        $rows = [
            ['INR', 'Indian Rupee',      '₹',   2],
            ['USD', 'US Dollar',         '$',   2],
            ['EUR', 'Euro',              '€',   2],
            ['GBP', 'British Pound',     '£',   2],
            ['JPY', 'Japanese Yen',      '¥',   0],
            ['AUD', 'Australian Dollar', 'A$',  2],
            ['CAD', 'Canadian Dollar',   'C$',  2],
            ['AED', 'UAE Dirham',        'AED', 2],
            ['SGD', 'Singapore Dollar',  'S$',  2],
        ];
        DB::table('currencies')->insert(array_map(fn ($row, $i) => [
            'code' => $row[0], 'name' => $row[1], 'symbol' => $row[2], 'decimals' => $row[3],
            'default_rate' => $row[0] === 'INR' ? 1 : null, 'enabled' => true,
            'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now,
        ], $rows, array_keys($rows)));
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
