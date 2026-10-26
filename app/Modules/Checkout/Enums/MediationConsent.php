<?php

namespace App\Modules\Checkout\Enums;

/**
 * Whether Kasia agrees to settle an unresolved complaint out of court. Without this statement the law treats her
 * as agreeing (art. 32 of the consumer ADR act), so an answer that leaves the dispute open always carries it.
 */
enum MediationConsent: string
{
    case Agrees = 'agrees';
    case Refuses = 'refuses';

    public function label(): string
    {
        return match ($this) {
            self::Agrees => 'Zgadzam się na mediację w Inspekcji Handlowej',
            self::Refuses => 'Nie zgadzam się na mediację',
        };
    }

    public function statement(): string
    {
        return match ($this) {
            self::Agrees => 'Zgadzam się na udział w pozasądowym rozwiązaniu tego sporu. Podmiot właściwy dla mojej firmy to Małopolski Wojewódzki Inspektor Inspekcji Handlowej w Krakowie, ul. Ujastek 7, 31-752 Kraków — tam możesz złożyć wniosek o mediację.',
            self::Refuses => 'Nie zgadzam się na udział w pozasądowym postępowaniu w sprawie rozwiązania tego sporu.',
        };
    }
}
