<?php

namespace Tests\Feature\Shared;

use App\Models\User;
use App\Modules\Shared\Http\Middleware\KeepShortWords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class KeepShortWordsTest extends TestCase
{
    use RefreshDatabase;

    private const NBSP = "\u{00A0}";

    private const WJ = "\u{2060}";

    public function test_short_words_numbers_ranges_dashes_and_arrows_keep_their_neighbour(): void
    {
        $this->assertSame('Glina i'.self::NBSP.'jedwab z'.self::NBSP.'jednej pary', KeepShortWords::text('Glina i jedwab z jednej pary'));
        $this->assertSame('i'.self::NBSP.'w'.self::NBSP.'domu', KeepShortWords::text('i w domu'));
        $this->assertSame('W'.self::NBSP.'10'.self::NBSP.'sekund', KeepShortWords::text('W 10 sekund'));
        $this->assertSame('6'.self::WJ.'–'.self::WJ.'12'.self::NBSP.'tygodni', KeepShortWords::text('6–12 tygodni'));
        $this->assertSame('obiegu'.self::NBSP.'— dwie', KeepShortWords::text('obiegu — dwie'));
        $this->assertSame('Zobacz'.self::NBSP.'→', KeepShortWords::text('Zobacz →'));
        $this->assertSame('Kawa', KeepShortWords::text('Kawa'));
    }

    public function test_only_text_between_tags_changes(): void
    {
        $html = '<div x-data="{ ids: [1], get n() { return this.ids.filter((id) => id > 0 && w a).length } }" title="a w b">i w domu</div>'
            .'<script>var a = "i w";</script><textarea name="t">i w</textarea><!-- i w --><p>a <b>z</b> b</p>';

        $out = KeepShortWords::html($html);

        $this->assertStringContainsString('title="a w b">i'.self::NBSP.'w'.self::NBSP.'domu</div>', $out);
        $this->assertStringContainsString('(id) => id > 0 && w a).length } }"', $out);
        $this->assertStringContainsString('<script>var a = "i w";</script>', $out);
        $this->assertStringContainsString('<textarea name="t">i w</textarea>', $out);
        $this->assertStringContainsString('<!-- i w -->', $out);
        $this->assertStringContainsString('<p>a'.self::NBSP.'<b>', $out);
    }

    public function test_shop_pages_get_it_and_the_panel_does_not(): void
    {
        config(['app.keep_short_words' => true]);
        $this->withoutVite();

        Route::get('/_typography', fn () => response('<p>Glina i jedwab</p>', 200, ['Content-Type' => 'text/html; charset=UTF-8']));
        $this->get('/_typography')->assertSee('<p>Glina i'.self::NBSP.'jedwab</p>', false);

        $panel = $this->actingAs(User::factory()->create())->get('/panel/kategorie')->assertOk()->getContent();
        $this->assertStringNotContainsString(self::NBSP, $panel);
    }
}
