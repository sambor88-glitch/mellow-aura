<?php

namespace App\Modules\Shared\Models\Concerns;

use App\Modules\Localization\Support\Locales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A model written in Polish in its own table, with its other languages in „<model>_translations”
 * (Product → ProductTranslation, one row per language).
 *
 * On a page in another language the fields listed in $translatable read from that language's row, so views keep
 * writing $product->name. A missing row reads as null, never as the Polish text: a model without its translation
 * stays out of that language (see translatedInto) instead of showing Polish under an English address.
 *
 * Writing always goes to the Polish columns; translations are saved through translations().
 *
 * @property list<string> $translatable
 */
trait HasTranslations
{
    /**
     * @return HasMany<Model, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(static::class.'Translation');
    }

    /** The row for a language, or null for Polish and for a language the model is not written in. */
    public function translation(?string $locale = null): ?Model
    {
        $locale ??= Locales::current();

        if ($locale === Locales::default()) {
            return null;
        }

        // All languages at once: a page reads several fields and hreflang asks about every language.
        if (! $this->relationLoaded('translations')) {
            $this->load('translations');
        }

        return $this->translations->firstWhere('locale', $locale);
    }

    public function hasTranslation(string $locale): bool
    {
        return $locale === Locales::default() || $this->translation($locale) !== null;
    }

    /**
     * The field in the language of the page, or in Polish when it has none. Only for what a customer already chose —
     * a basket line put in on a Polish page — never for what a page offers: the basket must name what is in it.
     */
    public function inPageLanguageOrPolish(string $key): mixed
    {
        return $this->getAttribute($key) ?? parent::getAttribute($key);
    }

    public function getAttribute($key)
    {
        if (in_array($key, $this->translatable, true) && Locales::current() !== Locales::default()) {
            return $this->translation()?->getAttribute($key);
        }

        return parent::getAttribute($key);
    }

    /**
     * Only what is written in the language: everything in Polish, only translated rows elsewhere.
     *
     * @param  Builder<static>  $query
     */
    public function scopeTranslatedInto(Builder $query, ?string $locale = null): void
    {
        $locale ??= Locales::current();

        if ($locale !== Locales::default()) {
            $query->whereHas('translations', fn (Builder $rows) => $rows->where('locale', $locale))
                ->with('translations');
        }
    }

    /**
     * /en/product/{slug} finds the product by its English slug, so an address in each language names the piece
     * in that language. The language is already set here: SetLocale runs before route bindings.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field ??= $this->getRouteKeyName();
        $locale = Locales::current();

        if ($locale !== Locales::default() && in_array($field, $this->translatable, true)) {
            return $this->newQuery()
                ->whereHas('translations', fn (Builder $rows) => $rows->where('locale', $locale)->where($field, $value))
                ->first();
        }

        return parent::resolveRouteBinding($value, $field);
    }
}
