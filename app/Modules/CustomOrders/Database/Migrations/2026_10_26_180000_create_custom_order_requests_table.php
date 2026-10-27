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
        // An idea sent through the form on /zamowienia-indywidualne. Its photos are media in the „photos”
        // collection on the private disk. Quantity, date and budget stay as the customer wrote them.
        Schema::create('custom_order_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email');
            $table->string('phone', 40)->nullable();
            // BriefKind value.
            $table->string('kind', 30)->index();
            $table->string('quantity', 60)->nullable();
            $table->string('needed_by', 60)->nullable();
            $table->string('budget', 60)->nullable();
            $table->text('description');
            $table->string('status', 20)->default('new')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_order_requests');
    }
};
