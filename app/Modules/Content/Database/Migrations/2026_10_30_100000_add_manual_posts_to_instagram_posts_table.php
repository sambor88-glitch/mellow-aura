<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Posts Kasia adds by hand in the panel (MA-274): the feed refresh leaves them alone, and they keep her order.
     */
    public function up(): void
    {
        Schema::table('instagram_posts', function (Blueprint $table) {
            $table->boolean('is_manual')->default(false)->after('external_id');
            $table->unsignedInteger('sort_order')->nullable()->after('is_manual');
        });
    }

    public function down(): void
    {
        Schema::table('instagram_posts', function (Blueprint $table) {
            $table->dropColumn(['is_manual', 'sort_order']);
        });
    }
};
