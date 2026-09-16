<?php

namespace Tests\Feature\Gifts;

use App\Models\User;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Gifts\Models\Bundle;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGiftsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->owner = User::factory()->create();
    }

    public function test_guests_are_sent_to_the_login(): void
    {
        $this->get('/panel/prezenty')->assertRedirect('/panel/logowanie');
        $this->post('/panel/prezenty/zestawy')->assertRedirect('/panel/logowanie');
    }

    public function test_the_page_lists_the_sets_with_their_parts_and_the_price_customers_see(): void
    {
        $mug = $this->variant('Kubki malowane ręcznie', '300 ml', 7900);
        $scrunchie = $this->variant('Scrunchies', 'Średnia', 6900);
        ProductVariant::factory()
            ->for(Product::factory()->state(['name' => 'Voucher kwotowy', 'category_id' => Category::factory()->create(['group' => CategoryGroup::Workshops])->id]))
            ->create(['label' => '150 zł']);
        $bundle = Bundle::factory()->create(['name' => 'Kubek i scrunchie', 'discount_percent' => 10]);
        $bundle->items()->create(['product_variant_id' => $mug->id, 'sort_order' => 0]);
        $bundle->items()->create(['product_variant_id' => $scrunchie->id, 'sort_order' => 1]);

        $this->actingAs($this->owner)
            ->get('/panel/prezenty')
            ->assertOk()
            ->assertSee('href="'.route('admin.gifts.edit').'"', false)
            ->assertSeeInOrder(['Zestawy prezentowe', 'value="Kubek i scrunchie"', 'value="'.$mug->id.'" selected', 'value="'.$scrunchie->id.'" selected', 'name="discount_percent" value="10"', 'Tak to zobaczą klienci:', '133,00 zł', '148,00 zł'], false)
            ->assertSeeInOrder(['Nowy zestaw', 'Dodaj zestaw na stronę', 'Teksty na stronie zestawów', 'Vouchery'])
            ->assertDontSee('Voucher kwotowy · 150 zł');
    }

    public function test_a_new_set_is_added_at_the_end_with_its_parts_in_order(): void
    {
        Bundle::factory()->create(['sort_order' => 4]);
        $first = $this->variant('Kadzielnice', 'Pszczoły', 9900);
        $second = $this->variant('Podstawki', 'Mała', 7900);

        $this->actingAs($this->owner)
            ->post('/panel/prezenty/zestawy', [
                'form' => 'nowy-zestaw',
                'name' => ' Wieczorny rytuał ',
                'description' => 'Na wieczór bez telefonu.',
                'parts' => [(string) $second->id, '', (string) $first->id, ''],
                'discount_percent' => '15 %',
                'is_published' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('panel_status', 'Wieczorny rytuał — widać na stronie zestawów');

        $bundle = Bundle::query()->where('name', 'Wieczorny rytuał')->sole();
        $this->assertSame(15, $bundle->discount_percent);
        $this->assertSame(5, $bundle->sort_order);
        $this->assertSame([$second->id, $first->id], $bundle->items->pluck('product_variant_id')->all());
    }

    public function test_a_set_is_edited_hidden_and_its_mistakes_come_back_to_its_card(): void
    {
        $bundle = Bundle::factory()->create(['name' => 'Poranek', 'discount_percent' => 10]);
        $cups = $this->variant('Filiżanki', 'Zestaw 2 szt.', 18900);
        $plate = $this->variant('Talerze', 'Deserowy', 11000);
        $bundle->items()->create(['product_variant_id' => $cups->id]);

        $this->actingAs($this->owner)
            ->from('/panel/prezenty')
            ->put('/panel/prezenty/zestawy/'.$bundle->id, ['form' => 'zestaw-'.$bundle->id, 'name' => 'Poranek', 'parts' => [(string) $cups->id, (string) $cups->id], 'discount_percent' => '95'])
            ->assertRedirect('/panel/prezenty#zestaw-'.$bundle->id)
            ->assertSessionHasErrorsIn('zestaw-'.$bundle->id, [
                'parts.0' => 'Każda rzecz może być w zestawie raz',
                'discount_percent' => 'Rabat to liczba od 0 do 90 — bez znaku %',
            ]);

        $this->put('/panel/prezenty/zestawy/'.$bundle->id, ['form' => 'zestaw-'.$bundle->id, 'name' => 'Poranek we dwoje', 'parts' => [(string) $plate->id], 'discount_percent' => '10'])
            ->assertSessionHasErrorsIn('zestaw-'.$bundle->id, ['parts' => 'Wybierz co najmniej dwie rzeczy do zestawu']);

        $this->put('/panel/prezenty/zestawy/'.$bundle->id, ['form' => 'zestaw-'.$bundle->id, 'name' => 'Poranek we dwoje', 'parts' => [(string) $plate->id, (string) $cups->id], 'discount_percent' => '12'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('panel_status', 'Poranek we dwoje — ukryty na stronie');

        $bundle->refresh();
        $this->assertSame(['Poranek we dwoje', 12, false], [$bundle->name, $bundle->discount_percent, $bundle->is_published]);
        $this->assertSame([$plate->id, $cups->id], $bundle->items->pluck('product_variant_id')->all());
    }

    public function test_texts_and_voucher_settings_are_saved(): void
    {
        $this->actingAs($this->owner)
            ->put('/panel/prezenty/teksty', ['text_bundles_heading' => "Glina i jedwab\r\nw jednym pudełku", 'text_bundles_lead' => '', 'text_gift_wrap_heading' => 'Pakowanie', 'text_gift_wrap_lead' => str_repeat('a', 401)])
            ->assertSessionHasErrorsIn('teksty', ['text_gift_wrap_lead' => 'Ten tekst zmieszczę do 400 znaków']);

        $this->put('/panel/prezenty/teksty', ['text_bundles_heading' => "Glina i jedwab\r\nw jednym pudełku", 'text_bundles_lead' => '', 'text_gift_wrap_heading' => 'Pakowanie', 'text_gift_wrap_lead' => 'Szare pudełko.'])
            ->assertRedirect('/panel/prezenty#teksty');

        $this->assertSame("Glina i jedwab\nw jednym pudełku", Setting::find('text_bundles_heading')->value);
        $this->assertNull(Setting::find('text_bundles_lead')->value);

        $this->put('/panel/prezenty/vouchery', ['voucher_validity_months' => '0', 'voucher_recipient_name_max_chars' => '80', 'voucher_dedication_max_chars' => '180'])
            ->assertSessionHasErrorsIn('vouchery', [
                'voucher_validity_months' => 'Wpisz liczbę miesięcy od 1 do 60',
                'voucher_recipient_name_max_chars' => 'Wpisz liczbę znaków od 10 do 60',
            ]);

        $this->put('/panel/prezenty/vouchery', ['voucher_validity_months' => '6', 'voucher_recipient_name_max_chars' => '30', 'voucher_dedication_max_chars' => '240', 'text_voucher_how_to_use' => ' napisz do mnie '])
            ->assertRedirect('/panel/prezenty#vouchery');

        $this->assertSame([6, 30, 240, 'napisz do mnie'], collect(['voucher_validity_months', 'voucher_recipient_name_max_chars', 'voucher_dedication_max_chars', 'text_voucher_how_to_use'])->map(fn (string $key) => Setting::find($key)->value)->all());
    }

    private function variant(string $product, string $label, int $price): ProductVariant
    {
        return ProductVariant::factory()->for(Product::factory()->state(['name' => $product]))->create(['label' => $label, 'price_gross' => $price, 'stock' => 3]);
    }
}
