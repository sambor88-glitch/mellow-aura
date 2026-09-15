<?php

namespace Tests\Feature;

use Tests\TestCase;

class NoIndexTest extends TestCase
{
    public function test_responses_carry_the_noindex_header_when_enabled(): void
    {
        config(['app.noindex' => true]);

        $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_responses_have_no_noindex_header_when_disabled(): void
    {
        config(['app.noindex' => false]);

        $this->get('/')->assertHeaderMissing('X-Robots-Tag');
    }
}
