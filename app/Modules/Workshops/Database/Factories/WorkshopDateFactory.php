<?php

namespace App\Modules\Workshops\Database\Factories;

use App\Modules\Workshops\Models\WorkshopDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkshopDate>
 */
class WorkshopDateFactory extends Factory
{
    protected $model = WorkshopDate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addWeeks(2)->setTime(11, 0);

        return [
            'workshop_code' => 'hand_building',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'seats' => 6,
        ];
    }
}
