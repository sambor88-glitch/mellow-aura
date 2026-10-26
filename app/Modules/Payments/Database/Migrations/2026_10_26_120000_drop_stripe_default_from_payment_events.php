<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The log of payment calls was written when the shop still paid through Stripe, and an event without a provider
 * fell back to it. Stripe is gone (MA-167, MA-99): every event names its provider, so the column has no default.
 * Events already logged keep the provider they were written with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_events', function (Blueprint $table) {
            $table->string('provider')->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('payment_events', function (Blueprint $table) {
            $table->string('provider')->default('stripe')->change();
        });
    }
};
