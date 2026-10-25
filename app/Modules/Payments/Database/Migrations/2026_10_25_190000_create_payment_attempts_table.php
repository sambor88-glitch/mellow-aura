<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every try at paying an order through Przelewy24. One order can have several tries (a refused BLIK code, a closed
 * bank page), and each gets its own session id, because Przelewy24 takes a session id only once.
 *
 * The amount is copied from the order when the try starts, in grosze or cents, so the notification is checked
 * against what Przelewy24 was asked for, not against an order that may have changed since.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('session_id', 100)->unique();
            $table->unsignedInteger('amount');
            $table->string('currency', 3);
            $table->string('token', 100)->nullable();
            // Przelewy24's own number of the transaction, known after a BLIK charge or from the notification.
            $table->unsignedBigInteger('provider_order_id')->nullable()->index();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
