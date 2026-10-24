<?php

namespace Tests\Unit\Shared;

use App\Modules\Shared\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    #[DataProvider('amounts')]
    public function test_formats_grosze_like_the_prototype(int $grosze, string $expected): void
    {
        $this->assertSame($expected, Money::format($grosze));
    }

    public static function amounts(): array
    {
        return [
            'whole' => [29900, "299,00\u{00A0}zł"],
            'with grosze' => [1250, "12,50\u{00A0}zł"],
            'single grosz' => [5, "0,05\u{00A0}zł"],
            'zero' => [0, "0,00\u{00A0}zł"],
            'negative' => [-1600, "-16,00\u{00A0}zł"],
        ];
    }
}
