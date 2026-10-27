<?php

namespace App\Modules\Shared\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The point of a photo that stays in view when a frame cuts it to another shape, set in the panel with the sliders
 * of <x-shared::focus-picker>. Both values run from 0 (left, top) to 100 (right, bottom); 50 is the middle.
 * A media library photo keeps it in its custom property `focus`.
 */
class FocalPoint
{
    public const CENTRE = 50;

    /**
     * @return array{x: int, y: int}
     */
    public static function of(?Media $photo): array
    {
        $focus = (array) ($photo?->getCustomProperty('focus') ?? []);

        return ['x' => self::clamp($focus['x'] ?? null), 'y' => self::clamp($focus['y'] ?? null)];
    }

    /**
     * The inline style for an <img class="object-cover">, e.g. `object-position: 50% 80%`.
     */
    public static function style(?Media $photo): string
    {
        return self::css(...self::of($photo));
    }

    public static function css(?int $x, ?int $y): string
    {
        return 'object-position: '.self::clamp($x).'% '.self::clamp($y).'%';
    }

    /**
     * Saves the point on a photo; the middle is the default, so it leaves no property behind. The caller saves the photo.
     *
     * @param  array{x: int, y: int}  $point
     */
    public static function set(Media $photo, array $point): void
    {
        $point = ['x' => self::clamp($point['x']), 'y' => self::clamp($point['y'])];

        $point === ['x' => self::CENTRE, 'y' => self::CENTRE]
            ? $photo->forgetCustomProperty('focus')
            : $photo->setCustomProperty('focus', $point);
    }

    /**
     * Validation rules for a `focus[x]` and `focus[y]` pair under the given field, e.g. `photo_focus.*`.
     *
     * @return array<string, list<string>>
     */
    public static function rules(string $field): array
    {
        return [
            $field => ['nullable', 'array'],
            // A dependent rule: Laravel puts the index of the row in place of a * in the field.
            $field.'.x' => ['required_with:'.$field, 'integer', 'between:0,100'],
            $field.'.y' => ['required_with:'.$field, 'integer', 'between:0,100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $field): array
    {
        return [
            $field.'.x' => 'Przesuń suwak kadru jeszcze raz',
            $field.'.y' => 'Przesuń suwak kadru jeszcze raz',
        ];
    }

    private static function clamp(mixed $value): int
    {
        return is_numeric($value) ? max(0, min(100, (int) $value)) : self::CENTRE;
    }
}
