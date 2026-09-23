<?php

namespace Tests\Unit\Shared;

use App\Modules\Shared\Support\Seo;
use PHPUnit\Framework\TestCase;

class SeoTest extends TestCase
{
    public function test_a_title_keeps_the_brand_when_it_fits(): void
    {
        $this->assertSame('Jedwab — rękodzieło z Krakowa | MellowAura', Seo::title('Jedwab', ' — rękodzieło z Krakowa'));
    }

    public function test_a_long_title_drops_the_brand_first(): void
    {
        $this->assertSame(
            'Zestaw 4 filiżanek z talerzykami — ceramika handmade',
            Seo::title('Zestaw 4 filiżanek z talerzykami', ' — ceramika handmade'),
        );
    }

    public function test_a_long_description_is_cut_at_a_word_boundary(): void
    {
        $description = Seo::description(str_repeat('glina i jedwab ', 20));

        $this->assertLessThanOrEqual(155, mb_strlen($description));
        $this->assertMatchesRegularExpression('/ (glina|i|jedwab)…$/u', $description);
    }

    public function test_a_short_description_only_loses_extra_whitespace(): void
    {
        $this->assertSame('Kubki i talerze.', Seo::description("  Kubki i\n talerze. "));
        $this->assertNull(Seo::description('   '));
    }
}
