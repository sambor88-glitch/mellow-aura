<?php

namespace Tests\Unit\Shared;

use App\Modules\Shared\Support\Plural;
use PHPUnit\Framework\TestCase;

class PluralTest extends TestCase
{
    public function test_polish_plural_forms(): void
    {
        $forms = fn (int $n) => Plural::count($n, 'wariant', 'warianty', 'wariantów');

        $this->assertSame('1 wariant', $forms(1));
        $this->assertSame('3 warianty', $forms(3));
        $this->assertSame('5 wariantów', $forms(5));
        $this->assertSame('12 wariantów', $forms(12));
        $this->assertSame('22 warianty', $forms(22));
        $this->assertSame('0 wariantów', $forms(0));
    }
}
