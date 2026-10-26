<?php

use App\Modules\Catalog\Enums\CategoryGroup;
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
        Schema::table('product_variants', function (Blueprint $table) {
            // A voucher goes out as a PDF by e-mail and needs no delivery, unless this size is printed and posted.
            // Goods from the shop always need one, whatever this says.
            $table->boolean('sent_by_post')->default(false)->after('stock');
        });

        // The couple's voucher from the prototype has a size „Wysyłka pocztą”.
        DB::table('product_variants')
            ->whereIn('product_id', DB::table('products')
                ->join('categories', 'categories.id', '=', 'products.category_id')
                ->where('categories.group', CategoryGroup::Workshops->value)
                ->select('products.id'))
            ->where('label', 'like', '%poczt%')
            ->update(['sent_by_post' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('sent_by_post');
        });
    }
};
