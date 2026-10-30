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
        // Every GDPR request Kasia carried out from „Klienci” (MA-241): a copy of the data or an erasure. The person
        // is kept only as CustomerKey — a keyed hash of her address — so the log itself forgets her too, yet a later
        // request from the same address still finds what was done before.
        Schema::create('privacy_requests', function (Blueprint $table) {
            $table->id();
            // export or erasure (PrivacyRequestType).
            $table->string('type', 16);
            $table->string('customer_key', 64)->index();
            // The panel account that did it.
            $table->string('performed_by', 120);
            // What the erasure changed, e.g. {"orders": 2, "paid_orders": 1}; the export lists what it held.
            $table->json('summary');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('privacy_requests');
    }
};
