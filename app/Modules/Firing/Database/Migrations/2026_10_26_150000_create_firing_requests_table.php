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
        // A batch reported through the form on /wypal-ceramiki-krakow. The service and its price are copied from
        // the price list, so a later price change does not change the quote the customer was given.
        Schema::create('firing_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email');
            $table->string('phone', 40)->nullable();
            // The price list row's code (KilnPrices::key); null when the customer asked for advice.
            $table->string('service_code', 60)->nullable();
            $table->string('service_label', 80)->nullable();
            // kg, piece or null for a flat price, as on the price list row.
            $table->string('unit', 30)->nullable();
            // Kilograms or pieces; null for a flat price or without a service.
            $table->decimal('quantity', 8, 2)->nullable();
            $table->unsignedInteger('unit_price')->nullable();
            // Grosze; null when Kasia prices the batch herself.
            $table->unsignedInteger('quote')->nullable();
            $table->date('drop_off_on')->nullable()->index();
            $table->text('message')->nullable();
            $table->string('status', 20)->default('new')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('firing_requests');
    }
};
