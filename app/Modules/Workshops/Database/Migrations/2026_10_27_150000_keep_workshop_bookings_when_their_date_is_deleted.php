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
        // A date whose bookings are all cancelled can be deleted (WorkshopDateController::destroy); the bookings stay
        // without it, so a late payment still finds a cancelled booking and is flagged for a refund (ConfirmBooking).
        Schema::table('workshop_bookings', function (Blueprint $table) {
            $table->dropForeign(['workshop_date_id']);
        });

        Schema::table('workshop_bookings', function (Blueprint $table) {
            $table->foreignId('workshop_date_id')->nullable()->change();
            $table->foreign('workshop_date_id')->references('id')->on('workshop_dates')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workshop_bookings', function (Blueprint $table) {
            $table->dropForeign(['workshop_date_id']);
        });

        Schema::table('workshop_bookings', function (Blueprint $table) {
            $table->foreignId('workshop_date_id')->nullable(false)->change();
            $table->foreign('workshop_date_id')->references('id')->on('workshop_dates')->restrictOnDelete();
        });
    }
};
