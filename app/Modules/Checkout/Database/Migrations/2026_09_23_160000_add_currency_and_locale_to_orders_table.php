<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An order remembers the currency it was paid in and the language it was placed in. Every amount on it — the
 * total, delivery, each item — is in that currency; the language decides the e-mails and the certificate.
 * Every order so far was placed in Polish and paid in złoty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->char('currency', 3)->default('PLN')->after('total_gross');
            $table->string('locale', 5)->default('pl')->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['currency', 'locale']);
        });
    }
};
