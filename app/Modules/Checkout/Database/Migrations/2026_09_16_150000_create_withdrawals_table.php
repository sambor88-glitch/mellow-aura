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
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            // Set when the number and the e-mail match an order. A statement counts even when they don't.
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number', 40);
            $table->string('name', 120);
            $table->string('email');
            // whole or part
            $table->string('scope', 16);
            $table->text('items')->nullable();
            // The date and time the acknowledgement quotes back to the customer.
            $table->timestamp('submitted_at');
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
