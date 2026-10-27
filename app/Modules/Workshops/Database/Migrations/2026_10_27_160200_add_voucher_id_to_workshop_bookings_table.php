<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A booking paid with a voucher code instead of money (MA-137). Cancelling it gives the voucher back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_bookings', function (Blueprint $table) {
            $table->foreignId('voucher_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workshop_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voucher_id');
        });
    }
};
