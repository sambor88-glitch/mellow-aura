<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * People waiting for a sold-out size to come back (MA-266). One row is one consent to one mail: it goes when the
 * stock returns, and the row goes with it. The e-mail is kept lower-case, so one address waits once per size.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            // The language of the page she signed up on: the mail and the link speak it.
            $table->string('locale', 5);
            // For the „Wypisz mnie” button; never shown in the panel.
            $table->char('token', 40)->unique();
            $table->timestamps();
            $table->unique(['product_variant_id', 'email']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_notifications');
    }
};
