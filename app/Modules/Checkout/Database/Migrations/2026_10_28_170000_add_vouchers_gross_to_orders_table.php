<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What amount vouchers paid of the order (MA-211), copied when the order is placed. total_gross stays the worth of
 * the goods and delivery, with VAT on each item as before: a voucher is a way to pay (a multi-purpose voucher),
 * not a discount. The gateway charges total_gross − vouchers_gross.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('vouchers_gross')->default(0)->after('total_gross');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('vouchers_gross');
        });
    }
};
