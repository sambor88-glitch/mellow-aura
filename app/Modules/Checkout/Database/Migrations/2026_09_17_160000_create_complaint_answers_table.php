<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Answers to complaints sent from the panel, word for word, as proof of what the customer got and when.
     */
    public function up(): void
    {
        Schema::create('complaint_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number')->nullable();
            $table->string('name');
            $table->string('email');
            $table->date('received_on');
            $table->string('decision');
            $table->string('remedy')->nullable();
            $table->text('details')->nullable();
            $table->string('mediation')->nullable();
            $table->text('letter');
            // Empty when the e-mail did not go out; the letter is kept anyway.
            $table->dateTime('emailed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_answers');
    }
};
