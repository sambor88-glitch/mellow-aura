<?php

namespace App\Modules\CustomOrders\Enums;

/**
 * Where an idea stands: just sent, being priced, waiting for the deposit, being made, and shipped — the last one
 * leaves the list of work.
 */
enum CustomOrderStatus: string
{
    case New = 'new';
    case Quoting = 'quoting';
    case Deposit = 'deposit';
    case InProgress = 'in_progress';
    case Shipped = 'shipped';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nowe',
            self::Quoting => 'Wycena',
            self::Deposit => 'Zaliczka',
            self::InProgress => 'W realizacji',
            self::Shipped => 'Wysłane',
        };
    }

    /**
     * The chip's colors, as on the order list.
     */
    public function chipClass(): string
    {
        return match ($this) {
            self::New => 'bg-rose text-ink',
            self::Quoting => 'bg-sand-dark text-lead',
            self::Deposit => 'bg-navy-soft text-navy-text',
            self::InProgress => 'bg-success-soft text-success-text',
            self::Shipped => 'bg-linen text-label',
        };
    }

    /**
     * The statuses still to work on, in the order the work goes.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::New, self::Quoting, self::Deposit, self::InProgress];
    }
}
