<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Complaints as they arrive by e-mail (MA-261), so the 14 days to answer show from the day one came in, not only once
 * Kasia answers. The answer points back to its complaint and closes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number')->nullable();
            $table->string('name');
            $table->string('email');
            $table->date('received_on');
            $table->text('description');
            $table->string('status')->default('open')->index();
            $table->timestamps();
        });

        Schema::table('complaint_answers', function (Blueprint $table) {
            $table->foreignId('complaint_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('complaint_answers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('complaint_id');
        });

        Schema::dropIfExists('complaints');
    }
};
