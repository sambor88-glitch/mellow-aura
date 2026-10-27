<?php

namespace App\Modules\Firing\Enums;

/**
 * Where a reported batch stands: just reported, accepted with a day to bring it, in the kiln, waiting to be
 * picked up, and picked up — the last one leaves the list of work.
 */
enum FiringRequestStatus: string
{
    case New = 'new';
    case Accepted = 'accepted';
    case InKiln = 'in_kiln';
    case Ready = 'ready';
    case Collected = 'collected';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nowe',
            self::Accepted => 'Przyjęte',
            self::InKiln => 'W piecu',
            self::Ready => 'Do odbioru',
            self::Collected => 'Odebrane',
        };
    }

    /**
     * The chip's colors, as on the order list.
     */
    public function chipClass(): string
    {
        return match ($this) {
            self::New => 'bg-rose text-ink',
            self::Accepted => 'bg-sand-dark text-lead',
            self::InKiln => 'bg-navy-soft text-navy-text',
            self::Ready => 'bg-success-soft text-success-text',
            self::Collected => 'bg-linen text-label',
        };
    }

    /**
     * The statuses still to work on, in the order the work goes.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::New, self::Accepted, self::InKiln, self::Ready];
    }
}
