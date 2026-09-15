<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_home_page_opens_the_shop_until_it_is_ported(): void
    {
        $this->get('/')->assertRedirect('/sklep');
    }
}
