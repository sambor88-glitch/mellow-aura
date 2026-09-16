<?php

namespace Tests\Unit\Gifts;

use App\Modules\Gifts\Support\VoucherCode;
use PHPUnit\Framework\TestCase;

class VoucherCodeTest extends TestCase
{
    public function test_a_code_is_easy_to_read_out_and_hard_to_guess(): void
    {
        $codes = array_map(fn () => VoucherCode::generate(), range(1, 200));

        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^MA-[2-9A-HJKMNP-Z]{4}-[2-9A-HJKMNP-Z]{4}$/', $code);
        }

        $this->assertCount(200, array_unique($codes));
    }
}
