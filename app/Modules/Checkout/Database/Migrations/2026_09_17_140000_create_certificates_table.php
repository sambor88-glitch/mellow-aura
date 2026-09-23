<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One certificate of uniqueness per handmade piece: three mugs on one order item are three certificates.
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('piece');
            // Set right after the row is saved, from its id: „2026/0012”.
            $table->string('number')->nullable()->unique();
            $table->timestamps();

            $table->unique(['order_item_id', 'piece']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
