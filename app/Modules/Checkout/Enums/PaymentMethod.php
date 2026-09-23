<?php

namespace App\Modules\Checkout\Enums;

enum PaymentMethod: string
{
    case Blik = 'blik';
    case OnlineTransfer = 'online_transfer';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';

    public function label(): string
    {
        return match ($this) {
            self::Blik => 'BLIK',
            self::OnlineTransfer => 'Przelewy24',
            self::Card => 'Karta',
            self::BankTransfer => 'Przelew tradycyjny',
        };
    }

    public function note(): string
    {
        return match ($this) {
            self::Blik => 'Kod z aplikacji banku',
            self::OnlineTransfer => 'Przelew online z 380 banków',
            self::Card => 'Visa, Mastercard, Apple Pay',
            self::BankTransfer => 'Wysyłka po zaksięgowaniu',
        };
    }
}
