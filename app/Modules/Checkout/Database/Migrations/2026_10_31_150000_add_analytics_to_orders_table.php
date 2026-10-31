<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The purchase measured from the server (MA-264). Saved when the order is placed, and only when the customer
 * allowed statistics: Google Analytics' browser id, its session, the items as the browser saw them and whether
 * ads may use her e-mail. analytics_sent_at marks the purchase as told to Google Analytics, so it goes once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('ga_client_id', 64)->nullable()->after('terms_accepted_at');
            $table->string('ga_session_id', 20)->nullable()->after('ga_client_id');
            $table->json('analytics_items')->nullable()->after('ga_session_id');
            $table->boolean('analytics_ad_user_data')->default(false)->after('analytics_items');
            $table->timestamp('analytics_sent_at')->nullable()->after('analytics_ad_user_data');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['ga_client_id', 'ga_session_id', 'analytics_items', 'analytics_ad_user_data', 'analytics_sent_at']);
        });
    }
};
