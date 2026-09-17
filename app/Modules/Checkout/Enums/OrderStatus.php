<?php

namespace App\Modules\Checkout\Enums;

/**
 * Where an order stands, in the five statuses of the mellowaura-panel skill. Each means one stage: waiting for
 * the payment, in the studio, on its way, done, or something to sort out.
 */
enum OrderStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Problem = 'problem';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nowe',
            self::InProgress => 'W realizacji',
            self::Shipped => 'Wysłane',
            self::Completed => 'Zakończone',
            self::Problem => 'Problem',
        };
    }

    /**
     * The chip's colors from the skill's table.
     */
    public function chipClass(): string
    {
        return match ($this) {
            self::New => 'bg-rose text-ink',
            self::InProgress => 'bg-sand-dark text-lead',
            self::Shipped => 'bg-navy-soft text-navy',
            self::Completed => 'bg-linen text-label',
            self::Problem => 'bg-alert text-error',
        };
    }
}
