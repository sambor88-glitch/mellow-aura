<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One name for the mug-and-scrunchie set, the one the home page uses (MA-222). GiftsSeeder finds a set
 * by its name, so without this it would add the set a second time. A name changed in the panel stays.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('bundles')->where('name', 'Kubek i scrunchie w jednym kolorze')->update(['name' => 'Kubek i scrunchie w jednym odcieniu']);
    }

    public function down(): void
    {
        DB::table('bundles')->where('name', 'Kubek i scrunchie w jednym odcieniu')->update(['name' => 'Kubek i scrunchie w jednym kolorze']);
    }
};
