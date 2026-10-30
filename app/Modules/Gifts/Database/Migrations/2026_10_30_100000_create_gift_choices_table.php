<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gift choice cards (MA-235): the buyer pays for a mug of a size, the person it is for chooses the text and the glaze.
     */
    public function up(): void
    {
        Schema::create('gift_choices', function (Blueprint $table) {
            $table->id();
            // Saved with the order; given when the payment comes in, like a voucher's. Empty until then.
            $table->string('code', 20)->nullable()->unique();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            // The size as named in the panel and as the buyer saw it, with what one card cost in grosze.
            $table->string('size_label', 60);
            $table->string('size_name', 80);
            $table->unsignedInteger('price_gross');
            $table->string('recipient_name', 60)->nullable();
            $table->string('sender_name', 60)->nullable();
            $table->text('dedication')->nullable();
            // Only for a card that goes by e-mail on a chosen day; a printed card has neither.
            $table->string('recipient_email')->nullable();
            $table->date('send_on')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->date('choose_by')->nullable();
            // 0, 1 after the reminder 14 days before the end, 2 after the one 3 days before.
            $table->unsignedTinyInteger('reminders_sent')->default(0);
            $table->timestamp('chosen_at')->nullable();
            $table->foreignId('fulfilment_order_id')->nullable()->constrained('orders')->nullOnDelete();
            // After the deadline Kasia decides: plain_mug or voucher.
            $table->string('resolution', 20)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['send_on', 'sent_at']);
            $table->index('choose_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_choices');
    }
};
