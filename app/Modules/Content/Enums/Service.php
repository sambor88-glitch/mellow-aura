<?php

namespace App\Modules\Content\Enums;

/**
 * The two services made from something the customer sends: a scarf sewn into a headband, a plant pressed
 * into clay. Their sentences, prices and steps live in settings under the service's value.
 */
enum Service: string
{
    case Scarf = 'scarf';
    case Imprint = 'imprint';

    public static function fromSlug(string $slug): ?self
    {
        foreach (self::cases() as $service) {
            if ($service->slug() === $slug) {
                return $service;
            }
        }

        return null;
    }

    /** The service's part of the panel address, e.g. /panel/uslugi/apaszka. */
    public function slug(): string
    {
        return match ($this) {
            self::Scarf => 'apaszka',
            self::Imprint => 'odcisk',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Scarf => 'Z Twojej apaszki',
            self::Imprint => 'Odcisk Twojej rośliny',
        };
    }

    public function route(): string
    {
        return match ($this) {
            self::Scarf => 'content.scarf',
            self::Imprint => 'content.imprint',
        };
    }

    /**
     * @param  'heading'|'lead'|'lead_2'|'note'|'prices'|'steps'  $name
     */
    public function setting(string $name): string
    {
        return match ($name) {
            'prices' => $this->value.'_service_prices',
            'steps' => $this->value.'_steps',
            default => 'text_'.$this->value.'_'.$name,
        };
    }

    /**
     * What a photo shows when Kasia leaves its description empty.
     *
     * @return array{before: string, after: string}
     */
    public function defaultAlts(): array
    {
        return match ($this) {
            self::Scarf => ['before' => 'Tkanina przysłana do przeróbki', 'after' => 'Rzecz uszyta z tej tkaniny'],
            self::Imprint => ['before' => 'Roślina przysłana do odcisku', 'after' => 'Ceramika z odciskiem tej rośliny'],
        };
    }
}
