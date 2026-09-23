<?php

namespace App\Modules\Checkout\Enums;

enum PaymentMethod: string
{
    case Blik = 'blik';
    case OnlineTransfer = 'online_transfer';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';

    /**
     * The ways to pay in a currency, in the order the checkout lists them. BLIK takes only złoty, and a bank
     * transfer goes to Kasia's złoty account; which account takes euro is still open (MA-124).
     *
     * @return list<self>
     */
    public static function for(string $currency): array
    {
        return match ($currency) {
            'PLN' => [self::Blik, self::OnlineTransfer, self::Card, self::BankTransfer],
            'EUR' => [self::Card, self::OnlineTransfer],
            default => [],
        };
    }

    /** What the customer reads at checkout, in the language of the page. The panel keeps label(). */
    public function title(): string
    {
        return __('checkout::payment.'.$this->value)[0];
    }

    public function hint(): string
    {
        return __('checkout::payment.'.$this->value)[1];
    }

    /** The name in the panel and in the e-mails to Kasia. */
    public function label(): string
    {
        return match ($this) {
            self::Blik => 'BLIK',
            self::OnlineTransfer => 'Przelewy24',
            self::Card => 'Karta',
            self::BankTransfer => 'Przelew tradycyjny',
        };
    }
}
