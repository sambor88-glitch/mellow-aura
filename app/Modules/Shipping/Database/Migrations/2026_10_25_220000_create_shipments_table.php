<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parcels sent through InPost's ShipX from the panel (MA-132). The order keeps its own tracking number and status;
 * this table holds what ShipX said about the parcel: its id, the service, the size and ShipX's own status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('shipx_id')->unique();
            // inpost_locker_standard or inpost_courier_standard.
            $table->string('service', 40);
            // small, medium or large — the locker's A, B and C.
            $table->string('size', 10);
            $table->string('target_point', 16)->nullable();
            $table->string('tracking_number', 40)->nullable();
            $table->string('status', 40);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
