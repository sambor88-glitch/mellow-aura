<?php

namespace Tests\Unit\Shared;

use App\Modules\Shared\Support\Money;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_whole_zloty_show_without_grosze_and_grosze_only_when_there_are_some(): void
    {
        $this->assertSame('69 zł', Money::format(6900));
        $this->assertSame('239 zł', Money::format(23900));
        $this->assertSame('79,90 zł', Money::format(7990));
        $this->assertSame('133,20 zł', Money::format(13320));
        $this->assertSame('0,05 zł', Money::format(5));
        $this->assertSame('0 zł', Money::format(0));
        $this->assertSame('-16 zł', Money::format(-1600));
        $this->assertSame('-0,50 zł', Money::format(-50));
    }

    public function test_euro_reads_the_polish_way_on_a_polish_page_and_the_english_way_on_an_english_one(): void
    {
        App::setLocale('pl');
        $this->assertSame('39 €', Money::format(3900, 'EUR'));
        $this->assertSame('43,50 €', Money::format(4350, 'EUR'));

        App::setLocale('en');
        $this->assertSame('€39', Money::format(3900, 'EUR'));
        $this->assertSame('€43.50', Money::format(4350, 'EUR'));
        // Złoty stay Polish whatever the page.
        $this->assertSame('79,90 zł', Money::format(7990));
    }

    public function test_it_writes_a_plain_decimal_with_grosze_for_machines(): void
    {
        $this->assertSame('239.00', Money::decimal(23900));
        $this->assertSame('0.05', Money::decimal(5));
    }

    public function test_panel_prices_are_typed_in_zloty_and_stored_in_grosze(): void
    {
        $this->assertSame(7900, Money::parse('79'));
        $this->assertSame(7990, Money::parse('79,9'));
        $this->assertSame(12905, Money::parse('129.05'));
        $this->assertSame('79', Money::input(7900));
        $this->assertSame('79,90', Money::input(7990));
    }
}
