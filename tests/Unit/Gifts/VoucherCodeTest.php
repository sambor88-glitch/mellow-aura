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

    public function test_a_typed_code_is_read_as_printed(): void
    {
        foreach (['MA-7KQ2-9XHT', 'ma-7kq2-9xht', ' 7kq2 9xht ', '7KQ29XHT', 'MA 7KQ2 9XHT', 'ma7kq29xht'] as $typed) {
            $this->assertSame('MA-7KQ2-9XHT', VoucherCode::normalize($typed), $typed);
        }

        $this->assertSame('MA7KQ2', VoucherCode::normalize('ma-7kq2'));
    }
}
