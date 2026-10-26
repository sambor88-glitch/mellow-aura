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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // MA-2026-1047, given right after the row gets its id.
            $table->string('number')->nullable()->unique();
            $table->string('status');
            $table->string('name');
            $table->string('email');
            $table->string('phone', 32);
            $table->string('shipping_method');
            // Street, postal code and city, only when the customer gave them.
            $table->json('shipping_address')->nullable();
            $table->string('locker_code', 16)->nullable();
            // Amounts in grosze, never float.
            $table->unsignedInteger('shipping_gross');
            $table->unsignedInteger('total_gross');
            $table->string('payment_method');
            $table->string('payment_status');
            $table->string('payment_provider_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('note')->nullable();
            $table->string('invoice_nip', 10)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
