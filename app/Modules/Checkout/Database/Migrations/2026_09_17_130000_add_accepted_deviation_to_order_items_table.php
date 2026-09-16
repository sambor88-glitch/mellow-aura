<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // A copy of the feature the customer accepted with its own checkbox, e.g. „nie do zmywarki” (terms §4.5).
            $table->string('accepted_deviation')->nullable()->after('custom_glaze');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('accepted_deviation');
        });
    }
};
