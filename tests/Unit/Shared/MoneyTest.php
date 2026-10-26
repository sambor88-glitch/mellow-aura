<?php

namespace Tests\Unit\Shared;

use App\Modules\Shared\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_formats_grosze_like_the_prototype(): void
    {
        $this->assertSame('239,00 zł', Money::format(23900));
        $this->assertSame('133,20 zł', Money::format(13320));
        $this->assertSame('0,05 zł', Money::format(5));
    }

    public function test_it_writes_a_plain_decimal_for_structured_data(): void
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
