<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kasia moves a paid booking to another date of the same workshop (MA-210). Each move stays in the booking's history
 * with both dates copied, so it still reads right after either date is deleted. moved_at on the booking is the last
 * move: the reminder counts from it like from the payment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_booking_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_workshop_date_id')->nullable()->constrained('workshop_dates')->nullOnDelete();
            $table->foreignId('to_workshop_date_id')->nullable()->constrained('workshop_dates')->nullOnDelete();
            $table->dateTime('from_starts_at');
            $table->dateTime('from_ends_at')->nullable();
            $table->dateTime('to_starts_at');
            $table->dateTime('to_ends_at')->nullable();
            $table->timestamps();
        });

        Schema::table('workshop_bookings', function (Blueprint $table) {
            $table->dateTime('moved_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('workshop_bookings', function (Blueprint $table) {
            $table->dropColumn('moved_at');
        });

        Schema::dropIfExists('workshop_booking_moves');
    }
};
