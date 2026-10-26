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
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            // Given when the payment comes in, e.g. MA-7KQ2-9XHT. Random, because a voucher is worth money.
            $table->string('code', 20)->unique();
            // Empty for a voucher that was not bought in the shop, e.g. at a fair.
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_name', 60)->nullable();
            $table->text('dedication')->nullable();
            $table->date('valid_until');
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
