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
        // Seats taken on a date by one order. A held booking counts until expires_at; the payment confirms it.
        Schema::create('workshop_bookings', function (Blueprint $table) {
            $table->id();
            // A date with bookings is never deleted from the calendar (WorkshopDateController::destroy).
            $table->foreignId('workshop_date_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('seats');
            // held, confirmed, released, overbooked (BookingStatus).
            $table->string('status', 20);
            $table->dateTime('expires_at');
            $table->dateTime('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['workshop_date_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshop_bookings');
    }
};
