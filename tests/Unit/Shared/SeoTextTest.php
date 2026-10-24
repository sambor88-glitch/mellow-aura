<?php

namespace Tests\Unit\Shared;

use App\Modules\Shared\Seo\SeoText;
use PHPUnit\Framework\TestCase;

class SeoTextTest extends TestCase
{
    public function test_title_takes_the_first_option_that_fits(): void
    {
        $this->assertSame('Kubek z napisem — ceramika handmade | MellowAura', SeoText::title([
            'Kubek z napisem — ceramika handmade | MellowAura',
            'Kubek z napisem — ceramika handmade',
        ]));
    }

    public function test_title_drops_the_brand_suffix_first(): void
    {
        $name = 'Zestaw 4 filiżanek z talerzykami';

        $this->assertSame("{$name} — ceramika handmade", SeoText::title([
            "{$name} — ceramika handmade | MellowAura",
            "{$name} — ceramika handmade",
            "{$name} | MellowAura",
            $name,
        ]));
    }

    public function test_title_longer_than_every_option_is_cut_at_a_word(): void
    {
        $title = SeoText::title(str_repeat('glina jedwab ', 8));

        $this->assertLessThanOrEqual(60, mb_strlen($title));
        $this->assertStringEndsNotWith(' ', $title);
        $this->assertMatchesRegularExpression('/(glina|jedwab)$/u', $title);
    }

    public function test_title_counts_polish_letters_as_one_character(): void
    {
        $title = str_repeat('ż', 60);

        $this->assertSame($title, SeoText::title($title));
    }

    public function test_short_description_stays_as_is_with_whitespace_collapsed(): void
    {
        $this->assertSame('Kubek z wbijanym napisem.', SeoText::description("  Kubek  z\nwbijanym napisem. "));
    }

    public function test_long_description_is_cut_at_a_word_and_ends_with_an_ellipsis(): void
    {
        $text = str_repeat('Ręcznie formowana ceramika, ', 10);

        $description = SeoText::description($text);

        $this->assertLessThanOrEqual(155, mb_strlen($description));
        $this->assertStringEndsWith('…', $description);
        $this->assertStringStartsWith(mb_substr($description, 0, -1), $text);
        $this->assertMatchesRegularExpression('/[^\s,]…$/u', $description);
    }

    public function test_description_of_exactly_155_characters_is_not_cut(): void
    {
        $text = str_repeat('a', 155);

        $this->assertSame($text, SeoText::description($text));
    }

    public function test_description_drops_html_and_handles_null(): void
    {
        $this->assertSame('Miska z gliny.', SeoText::description('<p>Miska <b>z gliny</b>.</p>'));
        $this->assertSame('', SeoText::description(null));
    }
}
