<?php

namespace App\Modules\Content\Models;

use App\Modules\Content\Enums\Service;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A „przed i po” pair on a service page: what the customer sent and what Kasia made of it.
 * It shows on the site once both photos are there.
 */
#[Fillable(['service', 'caption', 'before_alt', 'after_alt', 'sort_order'])]
class ServiceExample extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        foreach (['before', 'after'] as $collection) {
            $this->addMediaCollection($collection)->singleFile()->acceptsMimeTypes(['image/webp', 'image/jpeg', 'image/png']);
        }
    }

    /**
     * Both photos are cut to the same 4:5 shape, so the slider lays one exactly over the other.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->fit(Fit::Crop, 240, 300)->format('webp')->quality(80)->nonQueued();
        $this->addMediaConversion('card')->fit(Fit::Crop, 960, 1200)->format('webp')->quality(82)->nonQueued();
    }

    public function isComplete(): bool
    {
        return $this->hasMedia('before') && $this->hasMedia('after');
    }

    /**
     * @param  'before'|'after'  $side
     */
    public function alt(string $side): string
    {
        return $this->{$side.'_alt'} ?: $this->service->defaultAlts()[$side];
    }

    /**
     * @param  Builder<ServiceExample>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service' => Service::class,
            'sort_order' => 'integer',
        ];
    }
}
