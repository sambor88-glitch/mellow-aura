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
        // Kasia's price for the idea in grosze — the deposit link (MA-144) is counted from it — and her own note,
        // which the customer never sees.
        Schema::table('custom_order_requests', function (Blueprint $table) {
            $table->unsignedInteger('quote')->nullable()->after('description');
            $table->text('notes')->nullable()->after('quote');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_order_requests', function (Blueprint $table) {
            $table->dropColumn(['quote', 'notes']);
        });
    }
};
