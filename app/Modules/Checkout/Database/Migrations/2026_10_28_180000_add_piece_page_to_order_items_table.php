<?php

use App\Modules\Checkout\Support\PieceToken;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The page of one bought piece behind the QR code in the parcel (MA-234): its token, and what Kasia knows only
 * in the studio — when it was fired, the glaze, the plant pressed into it. Items already saved get a token too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('page_token', PieceToken::LENGTH)->nullable()->unique()->after('order_id');
            $table->date('fired_on')->nullable()->after('accepted_deviation');
            $table->string('glaze')->nullable()->after('fired_on');
            $table->string('plant')->nullable()->after('glaze');
        });

        DB::table('order_items')->whereNull('page_token')->orderBy('id')->pluck('id')
            ->each(fn (int $id) => DB::table('order_items')->where('id', $id)->update(['page_token' => PieceToken::generate()]));
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropUnique(['page_token']);
            $table->dropColumn(['page_token', 'fired_on', 'glaze', 'plant']);
        });
    }
};
