<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kasia took the material tiles off „O mnie” and the panel no longer edits them (MA-353), so the saved tiles go too.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'material_tiles')->delete();
    }
};
