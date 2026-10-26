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
        Schema::table('orders', function (Blueprint $table) {
            // Typed by Kasia when the parcel leaves the studio; the customer's mail links to InPost's tracking.
            $table->string('tracking_number', 40)->nullable()->after('locker_code');
            $table->timestamp('shipped_at')->nullable()->after('paid_at');
            $table->timestamp('completed_at')->nullable()->after('shipped_at');
            // What went wrong, for Kasia only.
            $table->string('problem_note', 500)->nullable()->after('note');
        });

        // Vouchers sent as PDFs alone were done the moment they were paid: the e-mail delivered them.
        DB::table('orders')
            ->where('shipping_method', 'email')
            ->where('payment_status', 'paid')
            ->where('status', 'in_progress')
            ->update(['status' => 'completed', 'completed_at' => DB::raw('paid_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('orders')->whereIn('status', ['shipped', 'completed', 'problem'])->update(['status' => 'in_progress']);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['tracking_number', 'shipped_at', 'completed_at', 'problem_note']);
        });
    }
};
