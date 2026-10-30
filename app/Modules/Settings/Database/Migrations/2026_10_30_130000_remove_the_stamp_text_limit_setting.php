<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The 22-character limit was for „Twój tekst” on the quote mugs, which is gone (MA-277). The mug configurator has
 * its own limit (mug_max_chars_per_line).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'stamp_text_max_chars')->delete();
    }
};
