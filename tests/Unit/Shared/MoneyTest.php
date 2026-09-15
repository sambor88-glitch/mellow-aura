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
}
