<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll reference data: ISO 4217 currencies and effective-dated exchange rates.
 *
 * - minor_units is the ISO 4217 exponent; payroll rounds each line to it, so
 *   precision is never assumed to be 2.
 * - The currency rows below are standard reference data (not statutory values)
 *   and are inserted idempotently.
 * - exchange_rates are entered by payroll staff with their source; a run stores
 *   the rates it used in its snapshot and never re-reads them.
 */
return new class extends Migration
{
    /** @var array<int, array{0: string, 1: string, 2: int}> code, name, minor units */
    private const CURRENCIES = [
        ['AED', 'UAE Dirham', 2], ['AUD', 'Australian Dollar', 2], ['BHD', 'Bahraini Dinar', 3],
        ['CAD', 'Canadian Dollar', 2], ['CHF', 'Swiss Franc', 2], ['CNY', 'Yuan Renminbi', 2],
        ['EUR', 'Euro', 2], ['GBP', 'Pound Sterling', 2], ['HKD', 'Hong Kong Dollar', 2],
        ['IDR', 'Rupiah', 2], ['INR', 'Indian Rupee', 2], ['JOD', 'Jordanian Dinar', 3],
        ['JPY', 'Yen', 0], ['KRW', 'Won', 0], ['KWD', 'Kuwaiti Dinar', 3],
        ['MYR', 'Malaysian Ringgit', 2], ['NZD', 'New Zealand Dollar', 2], ['OMR', 'Rial Omani', 3],
        ['PHP', 'Philippine Peso', 2], ['SAR', 'Saudi Riyal', 2], ['SGD', 'Singapore Dollar', 2],
        ['THB', 'Baht', 2], ['TND', 'Tunisian Dinar', 3], ['USD', 'US Dollar', 2],
        ['VND', 'Dong', 0],
    ];

    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->unique();
            $table->string('name');
            $table->unsignedTinyInteger('minor_units');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->char('base_currency', 3);
            $table->char('quote_currency', 3);
            // 1 unit of base_currency = rate units of quote_currency
            $table->decimal('rate', 18, 8);
            $table->date('rate_date');
            $table->string('source', 100);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['base_currency', 'quote_currency', 'rate_date', 'source'], 'exchange_rates_pair_date_unique');
            $table->index(['base_currency', 'quote_currency', 'rate_date'], 'exchange_rates_lookup_idx');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE exchange_rates ADD CONSTRAINT exchange_rates_rate_check CHECK (rate > 0)');
        }

        $now = now();
        DB::table('currencies')->insertOrIgnore(array_map(fn (array $c) => [
            'code' => $c[0], 'name' => $c[1], 'minor_units' => $c[2], 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ], self::CURRENCIES));
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
    }
};
