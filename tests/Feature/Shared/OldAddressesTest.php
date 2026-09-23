<?php

namespace Tests\Feature\Shared;

use App\Modules\Shared\Support\OldAddresses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OldAddressesTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_sumup_addresses_go_permanently_to_the_same_thing_on_the_new_site(): void
    {
        $this->get('/produkty')->assertStatus(301)->assertRedirect(route('shop.index'));
        $this->get('/kategoria/tekstylia')->assertRedirect(route('shop.category', 'jedwab'));
        $this->get('/produkt/kubki-malowane-recznie')->assertStatus(301)->assertRedirect(route('product.show', 'kubki-malowane'));
        $this->get('/strona/galeria-warsztaty-ceramiczne-krakow')->assertRedirect(route('workshops.index'));
        $this->get('/strona/warunki-korzystania')->assertRedirect(route('content.terms'));
        $this->get('/polityka-cookies')->assertRedirect(route('content.privacy'));
    }

    public function test_every_old_address_has_a_page_to_go_to(): void
    {
        foreach (OldAddresses::paths() as $path) {
            $this->assertNotNull(OldAddresses::target($path), $path);
        }
    }

    public function test_tracking_parameters_travel_along_and_case_or_a_trailing_slash_do_not_matter(): void
    {
        $this->get('/Strona/O-mnie/?utm_source=instagram&utm_medium=bio')
            ->assertStatus(301)
            ->assertRedirect(route('content.about').'?utm_source=instagram&utm_medium=bio');
    }

    public function test_the_old_cart_address_redirects_only_when_opened_and_new_addresses_are_left_alone(): void
    {
        $this->get('/koszyk')->assertStatus(301)->assertRedirect(route('shop.index'));
        $this->assertNotSame(301, $this->postJson('/koszyk', [])->getStatusCode());
        $this->get('/kontakt')->assertOk();
        $this->get('/nie-ma-takiej-strony')->assertNotFound();
        $this->assertNull(OldAddresses::target('/produkt/wazony'));
    }
}
