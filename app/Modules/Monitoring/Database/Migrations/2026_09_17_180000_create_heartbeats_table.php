<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The last sign of life from the scheduler and the queue worker, kept in the database so a cleared cache
     * does not hide a process that stopped.
     */
    public function up(): void
    {
        Schema::create('heartbeats', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->dateTime('beat_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heartbeats');
    }
};
