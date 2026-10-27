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
        // When the reminder the day before went out (SendReminders), so it never goes twice.
        Schema::table('workshop_bookings', function (Blueprint $table) {
            $table->dateTime('reminded_at')->nullable()->after('confirmed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workshop_bookings', function (Blueprint $table) {
            $table->dropColumn('reminded_at');
        });
    }
};
