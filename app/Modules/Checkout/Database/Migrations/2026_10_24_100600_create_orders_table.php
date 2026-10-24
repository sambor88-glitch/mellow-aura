<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // MA-2026-1047
            $table->string('number')->unique();
            $table->string('status')->default('new')->index();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('name');
            $table->string('shipping_method');
            $table->json('shipping_address')->nullable();
            $table->string('locker_code')->nullable();
            // Amounts in grosze.
            $table->unsignedInteger('shipping_gross')->default(0);
            $table->unsignedInteger('total_gross');
            $table->string('payment_status')->default('pending')->index();
            $table->string('payment_provider_id')->nullable()->unique();
            $table->text('note')->nullable();
            $table->string('invoice_nip', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
