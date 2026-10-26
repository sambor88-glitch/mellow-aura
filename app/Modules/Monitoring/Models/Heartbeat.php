<?php

namespace App\Modules\Monitoring\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The last sign of life from a process that runs apart from the website. The scheduler and the queue worker
 * watch each other, so one that stops is noticed while the other still runs.
 *
 * @property string $name
 * @property Carbon $beat_at
 */
#[Table('heartbeats', key: 'name', keyType: 'string', incrementing: false, timestamps: false)]
#[Fillable(['name', 'beat_at'])]
class Heartbeat extends Model
{
    public const SCHEDULER = 'scheduler';

    public const QUEUE = 'queue';

    public static function record(string $name): void
    {
        static::query()->upsert([['name' => $name, 'beat_at' => now()]], ['name'], ['beat_at']);
    }

    /**
     * Whole minutes since the last sign of life, or null when the process has never run here.
     */
    public static function silentFor(string $name): ?int
    {
        $beatAt = static::query()->find($name)?->beat_at;

        return $beatAt === null ? null : (int) $beatAt->diffInMinutes(now());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'beat_at' => 'datetime',
        ];
    }
}
