<?php

namespace Tests\Unit\Shared;

use App\Modules\Shared\Seo\Seo;
use PHPUnit\Framework\TestCase;

class SeoTest extends TestCase
{
    public function test_short_text_stays_as_is(): void
    {
        $this->assertSame('Wazony', Seo::limit('Wazony', 60));
    }

    public function test_long_text_is_cut_on_a_word_with_ellipsis_inside_the_limit(): void
    {
        $text = 'Ręcznie formowane wazony z ceramiki o organicznej, nieregularnej linii. Każdy egzemplarz to unikat — pełen drobnych asymetrii, które nadają mu charakter i duszę. Szkliwiony subtelnie, w odcieniach inspirowanych naturą: ziemią, wodą i światłem.';

        $limited = Seo::limit($text, 155);

        $this->assertLessThanOrEqual(155, mb_strlen($limited));
        $this->assertStringEndsWith('…', $limited);
        $this->assertStringStartsWith(mb_substr($limited, 0, -1), $text);
        $this->assertSame(' ', mb_substr($text, mb_strlen($limited) - 1, 1));
    }

    public function test_title_keeps_tagline_and_brand_when_they_fit(): void
    {
        $this->assertSame('Wazony — ceramika handmade | MellowAura', Seo::titleFor('Wazony', 'ceramika handmade'));
    }

    public function test_brand_suffix_goes_first_when_title_is_too_long(): void
    {
        $title = Seo::titleFor('Zestaw 4 filiżanek z talerzykami', 'ceramika handmade');

        $this->assertSame('Zestaw 4 filiżanek z talerzykami — ceramika handmade', $title);
    }

    public function test_voucher_title_is_name_and_brand(): void
    {
        $this->assertSame('Voucher kwotowy | MellowAura', Seo::titleFor('Voucher kwotowy'));
    }

    public function test_every_title_fits_in_60_characters(): void
    {
        $title = Seo::titleFor(str_repeat('Bardzo długa nazwa produktu ', 4), 'ceramika handmade');

        $this->assertLessThanOrEqual(60, mb_strlen($title));
    }
}
