<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The mug and the scrunchie no longer come in one colour (MA-347, Maciej's decision 1.11), so the set is
 * „w jednym zestawie” and its description stops promising a matching shade. GiftsSeeder finds a set by its
 * name, so the old name has to change here or the seeder would add the set a second time. A name or a
 * description Kasia already changed in the panel stays.
 */
return new class extends Migration
{
    private const OLD_NAME = 'Kubek i scrunchie w jednym odcieniu';

    private const NEW_NAME = 'Kubek i scrunchie w jednym zestawie';

    private const OLD_DESCRIPTION = 'Mój znak firmowy: glina i jedwab dobrane w jeden odcień. Kubek 300 ml i scrunchie w rozmiarze średnim, zapakowane razem.';

    private const NEW_DESCRIPTION = 'Mój znak firmowy: glina i jedwab z jednej pracowni. Kubek 300 ml i scrunchie w rozmiarze średnim, zapakowane razem.';

    public function up(): void
    {
        DB::table('bundles')->where('description', self::OLD_DESCRIPTION)->update(['description' => self::NEW_DESCRIPTION]);
        DB::table('bundles')->where('name', self::OLD_NAME)->update(['name' => self::NEW_NAME]);
    }

    public function down(): void
    {
        DB::table('bundles')->where('name', self::NEW_NAME)->update(['name' => self::OLD_NAME]);
        DB::table('bundles')->where('description', self::NEW_DESCRIPTION)->update(['description' => self::OLD_DESCRIPTION]);
    }
};
