<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every call from the payment provider, whatever we did with it. The house rule says to log them all,
 * and the unique event id makes the same call arriving twice count once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('stripe');
            $table->string('event_id')->unique();
            $table->string('type');
            $table->string('payment_provider_id')->nullable()->index();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload');
            $table->string('note')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
