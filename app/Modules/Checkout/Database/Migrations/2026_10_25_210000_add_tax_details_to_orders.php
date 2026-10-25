<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the tax office and the WSTO counter (MA-129) need from every order, kept from the first order on: adding it
 * later would mean guessing it for orders already placed.
 *
 * - delivery_country: where the goods go. Cross-border sales to consumers in the EU count towards the 10 000 EUR threshold.
 * - eur_rate / eur_rate_on: the NBP rate in force when the order was placed, so the order's value in euro never
 *   changes when the rate does.
 * - vat_rate / vat_exemption on each item: the rate charged, zero included, and the legal basis when it is zero.
 *
 * Every order so far went to Poland (Polish postal codes and InPost only) and was placed under Kasia's VAT exemption.
 */
return new class extends Migration
{
    private const EXEMPTION = 'zwolnienie podmiotowe, art. 113 ust. 1 ustawy o VAT';

    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->char('delivery_country', 2)->default('PL')->after('shipping_address');
            $table->decimal('eur_rate', 8, 4)->nullable()->after('currency');
            $table->date('eur_rate_on')->nullable()->after('eur_rate');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('vat_rate', 5, 2)->default(0)->after('unit_price_gross');
            $table->string('vat_exemption', 160)->nullable()->after('vat_rate');
        });

        DB::table('order_items')->whereNull('vat_exemption')->update(['vat_exemption' => self::EXEMPTION]);
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['vat_rate', 'vat_exemption']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_country', 'eur_rate', 'eur_rate_on']);
        });
    }
};
