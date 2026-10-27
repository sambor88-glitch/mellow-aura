<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // When Kasia cancelled the booking in the panel (CancelBooking); the status says cancelled from then on.
        Schema::table('workshop_bookings', function (Blueprint $table) {
            $table->dateTime('cancelled_at')->nullable()->after('reminded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workshop_bookings', function (Blueprint $table) {
            $table->dropColumn('cancelled_at');
        });
    }
};
