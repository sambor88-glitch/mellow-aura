<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A date on the workshop calendar. The workshop itself (name, price) is a row of the price list in settings.
        Schema::create('workshop_dates', function (Blueprint $table) {
            $table->id();
            // The price list row's code (WorkshopTypes::key), e.g. hand_building.
            $table->string('workshop_code', 60)->index();
            // Local time in Warsaw; the site gives it out as ISO 8601 with the offset for Event and CourseInstance.
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedTinyInteger('seats');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshop_dates');
    }
};
