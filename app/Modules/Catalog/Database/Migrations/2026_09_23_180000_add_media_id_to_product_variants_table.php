<?php

use App\Modules\Catalog\Models\Product;
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
            // The product photo that shows this variant; picking it on the product page opens the gallery there.
            // null means the product's first photo. A deleted photo leaves the variant without one.
            $table->foreignId('media_id')->nullable()->after('stock')->constrained('media')->nullOnDelete();
        });

        // The quote mugs from the prototype each have their own photo.
        $photos = [
            'Królowa matka' => 'kubek-krolowa-matka.webp',
            'Ochujeję' => 'kubek-ochujeje.webp',
            'No ocipieje zaraz' => 'kubek-ocipieje.webp',
            'Nie powinnam, ale…' => 'kubek-nie-powinnam.webp',
        ];
        $product = DB::table('products')->where('slug', 'kubki-z-cytatem')->value('id');

        foreach ($product === null ? [] : $photos as $label => $file) {
            $media = DB::table('media')
                ->where('model_type', Product::class)
                ->where('model_id', $product)
                ->where('collection_name', 'images')
                ->where('file_name', $file)
                ->value('id');

            if ($media !== null) {
                DB::table('product_variants')->where('product_id', $product)->where('label', $label)->whereNull('media_id')->update(['media_id' => $media]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('media_id');
        });
    }
};
