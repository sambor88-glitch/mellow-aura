<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every attempt to pay for an order. An order can have several — a refused BLIK code, a closed tab — and
 * each one goes to Przelewy24 under its own session id. Written before the provider hears of it, as
 * Przelewy24 asks, so the amount we expect is on record before any money moves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('przelewy24');
            $table->string('session_id', 100)->unique();
            // Grosze or cents, exactly as the order keeps them.
            $table->unsignedInteger('amount');
            $table->char('currency', 3);
            $table->string('method');
            $table->string('status');
            $table->string('token')->nullable();
            // Przelewy24's own number for the transaction, known only from its notification (MA-102).
            $table->unsignedBigInteger('provider_order_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
