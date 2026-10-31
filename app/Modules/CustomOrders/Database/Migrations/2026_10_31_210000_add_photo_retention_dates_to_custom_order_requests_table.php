<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // When the piece went out — the two years the photos are kept count from it (MA-312) — and when the photos
        // were deleted, so the panel says so instead of showing an empty gallery.
        Schema::table('custom_order_requests', function (Blueprint $table) {
            $table->dateTime('shipped_at')->nullable()->after('status');
            $table->dateTime('photos_deleted_at')->nullable()->after('shipped_at');
        });

        // Ideas shipped before this column existed: the last save is the closest date there is.
        DB::table('custom_order_requests')->where('status', 'shipped')->update(['shipped_at' => DB::raw('updated_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_order_requests', function (Blueprint $table) {
            $table->dropColumn(['shipped_at', 'photos_deleted_at']);
        });
    }
};
