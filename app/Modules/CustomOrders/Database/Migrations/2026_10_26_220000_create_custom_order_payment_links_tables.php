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
        // A link Kasia sends from the panel for a part of the quote or all of it, in grosze (MA-144). The token is
        // the link's address; one link at a time is open, a new one cancels the old.
        Schema::create('custom_order_payment_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_order_request_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->unsignedInteger('amount');
            // open, paid, cancelled (PaymentLinkStatus).
            $table->string('status', 20)->default('open');
            $table->dateTime('expires_at');
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->index(['custom_order_request_id', 'status']);
        });

        // Each try to pay a link is an order in Checkout. An older try can still be paid after a newer one started,
        // so every order is kept here, with the amount it pays and when the payment came.
        Schema::create('custom_order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_order_payment_link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_order_payments');
        Schema::dropIfExists('custom_order_payment_links');
    }
};
