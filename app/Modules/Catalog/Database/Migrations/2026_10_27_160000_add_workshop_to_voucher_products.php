<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a workshop voucher is for (MA-137): the workshop from the price list (workshop_types) and, per size, how many
 * seats it books — „Dla dwóch osób” is two seats, a couple is one. Gifts\Actions\IssueVouchers copies both onto each
 * voucher. A voucher product without a workshop (an amount voucher) books nothing by itself.
 * The seeded vouchers get theirs here; anything Kasia changed in the panel stays.
 */
return new class extends Migration
{
    private const WORKSHOPS = ['voucher-lepienie' => 'hand_building', 'voucher-para' => 'couples'];

    private const SEATS = ['Dla jednej osoby' => 1, 'Dla dwóch osób' => 2, 'Dla trzech osób' => 3];

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('workshop_code', 40)->nullable()->after('stamp_enabled');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedTinyInteger('voucher_seats')->nullable()->after('sent_by_post');
        });

        foreach (self::WORKSHOPS as $slug => $code) {
            $productId = DB::table('products')->where('slug', $slug)->whereNull('workshop_code')->value('id');

            if ($productId === null) {
                continue;
            }

            DB::table('products')->where('id', $productId)->update(['workshop_code' => $code]);

            foreach (DB::table('product_variants')->where('product_id', $productId)->get(['id', 'label']) as $variant) {
                DB::table('product_variants')->where('id', $variant->id)->update(['voucher_seats' => self::SEATS[$variant->label] ?? 1]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('voucher_seats');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('workshop_code');
        });
    }
};
