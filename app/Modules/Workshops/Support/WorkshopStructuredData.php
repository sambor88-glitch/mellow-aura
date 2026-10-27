<?php

namespace App\Modules\Workshops\Support;

use App\Modules\Shared\Support\Money;
use App\Modules\Workshops\Models\WorkshopDate;
use Illuminate\Support\Collection;
use Spatie\SchemaOrg\CourseInstance;
use Spatie\SchemaOrg\ItemAvailability;
use Spatie\SchemaOrg\Schema;

/**
 * JSON-LD Course for /warsztaty-ceramiczne-krakow: each upcoming date as a CourseInstance with ISO dates
 * and the price from the price list. Google no longer shows course results (specification), so the markup is
 * optional, but it must stay correct. The location is the city only — never the studio's address.
 */
class WorkshopStructuredData
{
    /**
     * @param  Collection<int, array{date: WorkshopDate, workshop: array{name: string, price_gross: int}}>  $dates
     */
    public static function for(string $url, ?string $description, Collection $dates): ?string
    {
        if ($dates->isEmpty()) {
            return null;
        }

        $course = Schema::course()
            ->name('Warsztaty ceramiczne w Krakowie')
            ->url($url)
            ->provider(Schema::localBusiness()->identifier(url('/').'/#business'))
            ->hasCourseInstance($dates->map(fn (array $entry) => self::instance($url, $entry['date'], $entry['workshop']))->all());

        if (filled($description)) {
            $course->description(trim((string) preg_replace('/\s+/u', ' ', $description)));
        }

        return $course->toScript();
    }

    /**
     * @param  array{name: string, price_gross: int}  $workshop
     */
    private static function instance(string $url, WorkshopDate $date, array $workshop): CourseInstance
    {
        $instance = Schema::courseInstance()
            ->name($workshop['name'])
            ->courseMode('onsite')
            ->startDate($date->starts_at->toIso8601String())
            ->location(Schema::place()->address(Schema::postalAddress()->addressLocality('Kraków')->addressCountry('PL')))
            ->offers(Schema::offer()
                ->price(Money::decimal($workshop['price_gross']))
                ->priceCurrency('PLN')
                ->availability($date->seatsLeft() > 0 ? ItemAvailability::InStock : ItemAvailability::SoldOut)
                ->url($url));

        if ($date->ends_at !== null) {
            $instance->endDate($date->ends_at->toIso8601String());
        }

        return $instance;
    }
}
