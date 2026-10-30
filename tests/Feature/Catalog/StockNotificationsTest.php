<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Http\Controllers\StockNotificationController;
use App\Modules\Catalog\Mail\BackInStock;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\StockNotification;
use App\Modules\Customers\Models\PrivacyRequest;
use App\Modules\Customers\Support\CustomerKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StockNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Category $vases;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->vases = Category::factory()->create(['name' => 'Wazony i patery', 'slug' => 'wazony-i-patery', 'group' => CategoryGroup::Ceramics]);
        $this->vases->translations()->create(['locale' => 'en', 'name' => 'Vases', 'slug' => 'vases']);
    }

    public function test_a_sold_out_size_asks_for_an_e_mail_instead_of_the_basket(): void
    {
        [$product, $low, $tall] = $this->vase();

        $this->get('/produkt/wazony?wariant='.$tall->id)
            ->assertOk()
            ->assertDontSee('<form id="add-to-cart"', false)
            ->assertSeeInOrder(['id="powiadom"', 'wyprzedane', 'Ta rzecz się wyprzedała', 'Twój e-mail', 'Zgadzam się na jeden mail, gdy ta rzecz wróci', 'name="website"', 'Napisz, gdy wróci'], false)
            ->assertSee('<input type="hidden" name="variant_id" value="'.$tall->id.'">', false);

        // A size on the shelf has the basket and no sign-up.
        $this->get('/produkt/wazony?wariant='.$low->id)
            ->assertOk()
            ->assertSee('<form id="add-to-cart"', false)
            ->assertDontSee('id="powiadom"', false);
    }

    public function test_signing_up_keeps_the_address_for_this_one_size_and_offers_to_step_back(): void
    {
        [, , $tall] = $this->vase();

        $this->from('/produkt/wazony?wariant='.$tall->id)
            ->post('/powiadom-o-dostepnosci', ['variant_id' => $tall->id, 'email' => ' Ania@Example.com ', 'consent' => '1'])
            ->assertRedirect('/produkt/wazony?wariant='.$tall->id.'#powiadom')
            ->assertSessionHas('restock_status', 'subscribed');

        $notification = StockNotification::sole();
        $this->assertSame([$tall->id, 'ania@example.com', 'pl'], [$notification->product_variant_id, $notification->email, $notification->locale]);
        $this->assertSame(40, strlen($notification->token));

        $this->get('/produkt/wazony?wariant='.$tall->id)
            ->assertSee('Zapisane. Napiszę, gdy ta rzecz wróci na półkę.')
            ->assertSee('action="'.route('stock-notifications.destroy', $notification->token).'"', false)
            ->assertDontSee('name="email"', false);

        // Coming back later, the page still knows she waits.
        $this->get('/produkt/wazony?wariant='.$tall->id)
            ->assertSee('Czekasz na tę rzecz — napiszę, gdy wróci.')
            ->assertSee('action="'.route('stock-notifications.destroy', $notification->token).'"', false);
    }

    public function test_the_same_address_twice_waits_once_and_hears_the_same_answer(): void
    {
        [, , $tall] = $this->vase();
        $form = ['variant_id' => $tall->id, 'email' => 'ania@example.com', 'consent' => '1'];

        $this->post('/powiadom-o-dostepnosci', $form)->assertSessionHas('restock_status', 'subscribed');
        $token = StockNotification::sole()->token;

        $this->flushSession();
        $this->post('/powiadom-o-dostepnosci', [...$form, 'email' => 'ANIA@example.com'])->assertSessionHas('restock_status', 'subscribed');

        $this->assertSame($token, StockNotification::sole()->token);
    }

    public function test_the_form_asks_for_the_address_and_the_consent_and_a_bot_is_kept_out(): void
    {
        [, , $tall] = $this->vase();

        $this->from('/produkt/wazony?wariant='.$tall->id)
            ->post('/powiadom-o-dostepnosci', ['variant_id' => $tall->id, 'email' => 'ania'])
            ->assertRedirect('/produkt/wazony?wariant='.$tall->id.'#powiadom')
            ->assertSessionHasErrors([
                'email' => 'Adres e-mail bez małpy — sprawdź, czy nie uciekła',
                'consent' => 'Zaznacz zgodę na ten jeden mail — bez niej nie mogę napisać',
            ]);

        // The hidden field filled in: the same answer as a person gets, and nothing is kept.
        $this->post('/powiadom-o-dostepnosci', ['variant_id' => $tall->id, 'email' => 'bot@example.com', 'consent' => '1', 'website' => 'https://spam.example'])
            ->assertSessionHas('restock_status', 'subscribed');

        $this->assertSame(0, StockNotification::query()->count());
    }

    public function test_only_a_sold_out_size_takes_sign_ups_and_too_many_tries_are_stopped(): void
    {
        [, $low, $tall] = $this->vase();

        $this->post('/powiadom-o-dostepnosci', ['variant_id' => $low->id, 'email' => 'ania@example.com', 'consent' => '1'])->assertNotFound();

        // The refused try counts too: six a minute in all.
        foreach (range(1, 5) as $try) {
            $this->post('/powiadom-o-dostepnosci', ['variant_id' => $tall->id, 'email' => "ania{$try}@example.com", 'consent' => '1'])->assertRedirect();
        }

        $this->post('/powiadom-o-dostepnosci', ['variant_id' => $tall->id, 'email' => 'ania6@example.com', 'consent' => '1'])->assertTooManyRequests();
    }

    public function test_stepping_back_removes_the_address(): void
    {
        [, , $tall] = $this->vase();
        $this->post('/powiadom-o-dostepnosci', ['variant_id' => $tall->id, 'email' => 'ania@example.com', 'consent' => '1']);
        $token = StockNotification::sole()->token;

        $this->delete('/powiadom-o-dostepnosci/'.$token)
            ->assertRedirect('/produkt/wazony?wariant='.$tall->id.'#powiadom')
            ->assertSessionHas('restock_status', 'unsubscribed')
            ->assertSessionMissing(StockNotificationController::SESSION.'.'.$tall->id);

        $this->assertSame(0, StockNotification::query()->count());
        $this->get('/produkt/wazony?wariant='.$tall->id)->assertSee('Wypisane. Nie napiszę w tej sprawie.')->assertSee('name="email"', false);

        // A token that is gone leads to the shop.
        $this->delete('/powiadom-o-dostepnosci/'.$token)->assertRedirect('/sklep');
    }

    public function test_pieces_back_on_the_shelf_send_each_one_mail_in_her_language_and_forget_her(): void
    {
        Mail::fake();
        [$product, $low, $tall] = $this->vase();
        $this->post('/powiadom-o-dostepnosci', ['variant_id' => $tall->id, 'email' => 'ania@example.com', 'consent' => '1']);
        $this->post('/en/notify-me', ['variant_id' => $tall->id, 'email' => 'jane@example.com', 'consent' => '1']);
        $this->assertSame(['ania@example.com' => 'pl', 'jane@example.com' => 'en'], StockNotification::query()->orderBy('email')->pluck('locale', 'email')->all());
        $owner = User::factory()->create();

        $this->actingAs($owner)->get('/panel/produkty')->assertSee('2 osoby czekają na powrót (Wysoki 24 cm: 2)');

        // Saving without new pieces sends nothing.
        $this->actingAs($owner)->put('/panel/produkty/'.$product->id, $this->form($product, $low, $tall, tallStock: '0'))->assertRedirect();
        Mail::assertNothingQueued();
        $this->assertSame(2, StockNotification::query()->count());

        $this->actingAs($owner)->put('/panel/produkty/'.$product->id, $this->form($product, $low, $tall, tallStock: '2'))->assertRedirect();

        Mail::assertQueued(BackInStock::class, 2);
        Mail::assertQueued(BackInStock::class, fn (BackInStock $mail) => $mail->hasTo('ania@example.com')
            && $mail->locale === 'pl'
            && $mail->url === url('/produkt/wazony').'?wariant='.$tall->id
            && $mail->itemName() === 'Wazony (Wysoki 24 cm)');
        Mail::assertQueued(BackInStock::class, fn (BackInStock $mail) => $mail->hasTo('jane@example.com')
            && $mail->locale === 'en'
            && $mail->url === url('/en/product/vases').'?wariant='.$tall->id
            && $mail->itemName() === 'Vases (Tall 24 cm)');
        $this->assertSame(0, StockNotification::query()->count());
        $this->actingAs($owner)->get('/panel/produkty')->assertDontSee('czekają na powrót');
    }

    public function test_the_mail_says_it_is_back_with_a_link_and_that_the_address_is_gone(): void
    {
        $polish = new BackInStock('pl', 'Wazony', 'Wysoki 24 cm', 'https://mellow-aura.com/produkt/wazony?wariant=2', 'ania@example.com');

        $polish->assertHasSubject('Znów na półce: Wazony (Wysoki 24 cm)');
        $polish->assertSeeInHtml('Czekałaś na');
        $polish->assertSeeInHtml('href="https://mellow-aura.com/produkt/wazony?wariant=2"', false);
        $polish->assertSeeInHtml('Zobacz w sklepie');
        $polish->assertSeeInText('Twój adres usunęłam z listy czekających.');
        $this->assertSame('Znów na półce: Wazony (Wysoki 24 cm) — do ania@example.com', $polish->description());

        $english = new BackInStock('en', 'Vases', 'Tall 24 cm', 'https://mellow-aura.com/en/product/vases?wariant=2', 'jane@example.com');
        $english->assertHasSubject('Back on the shelf: Vases (Tall 24 cm)');
        $english->assertSeeInHtml('See it in the shop');
        $english->assertSeeInText('I’ve taken your address off the waiting list.');
    }

    public function test_the_english_page_signs_up_in_english_and_a_size_without_a_euro_price_takes_no_one(): void
    {
        [, , $tall] = $this->vase();

        $this->get('/en/product/vases?wariant='.$tall->id)
            ->assertOk()
            ->assertSeeInOrder(['This one has sold out', 'Your e-mail', 'I agree to one e-mail when this piece is back', 'Tell me when it’s back'])
            ->assertSee('action="'.url('/en/notify-me').'"', false);

        $this->from('/en/product/vases?wariant='.$tall->id)
            ->post('/en/notify-me', ['variant_id' => $tall->id, 'email' => 'jane@example.com', 'consent' => '1'])
            ->assertRedirect('/en/product/vases?wariant='.$tall->id.'#powiadom');
        $this->assertSame('en', StockNotification::sole()->locale);

        $this->get('/en/product/vases?wariant='.$tall->id)->assertSee('Saved. I’ll write when this piece is back on the shelf.');

        $tall->prices()->delete();
        $this->post('/en/notify-me', ['variant_id' => $tall->id, 'email' => 'john@example.com', 'consent' => '1'])->assertNotFound();
    }

    public function test_she_shows_in_customers_and_erasing_her_forgets_what_she_waits_for(): void
    {
        [, , $tall] = $this->vase();
        $this->post('/powiadom-o-dostepnosci', ['variant_id' => $tall->id, 'email' => 'ania@example.com', 'consent' => '1']);
        $this->flushSession();
        $this->post('/powiadom-o-dostepnosci', ['variant_id' => $tall->id, 'email' => 'basia@example.com', 'consent' => '1']);
        $owner = User::factory()->create(['name' => 'Kasia']);
        $key = CustomerKey::of('ania@example.com');

        $this->actingAs($owner)->get('/panel/klienci')->assertSee('ania@example.com')->assertSee('czeka na 1 rzecz');
        $this->actingAs($owner)->get('/panel/klienci/'.$key)->assertSeeInOrder(['Czeka na powrót', 'Wazony (Wysoki 24 cm)', 'mail po polsku']);

        $copy = json_decode($this->actingAs($owner)->post('/panel/klienci/'.$key.'/kopia-danych')->assertOk()->getContent(), true);
        $this->assertSame([['product' => 'Wazony', 'variant' => 'Wysoki 24 cm', 'language' => 'pl']], array_map(fn (array $row) => array_intersect_key($row, array_flip(['product', 'variant', 'language'])), $copy['stock_notifications']));

        $this->actingAs($owner)->delete('/panel/klienci/'.$key, ['confirm_email' => 'ania@example.com'])->assertRedirect('/panel/klienci');

        $this->assertSame(['basia@example.com'], StockNotification::query()->pluck('email')->all());
        $this->assertSame(1, PrivacyRequest::query()->latest('id')->first()->summary['stock_notifications']);
    }

    /**
     * Two sizes, the tall one sold out, both in English with a euro price.
     *
     * @return array{Product, ProductVariant, ProductVariant}
     */
    private function vase(): array
    {
        $product = Product::factory()->create(['slug' => 'wazony', 'name' => 'Wazony', 'category_id' => $this->vases->id]);
        $product->translations()->create(['locale' => 'en', 'name' => 'Vases', 'slug' => 'vases']);
        $low = ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Niski 16 cm', 'price_gross' => 23900, 'stock' => 3]);
        $tall = ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Wysoki 24 cm', 'price_gross' => 29900, 'stock' => 0]);

        foreach ([[$low, 'Low 16 cm', 5900], [$tall, 'Tall 24 cm', 7400]] as [$variant, $label, $euro]) {
            $variant->translations()->create(['locale' => 'en', 'label' => $label]);
            $variant->prices()->create(['currency' => 'EUR', 'amount_minor' => $euro]);
        }

        return [$product->fresh(), $low, $tall];
    }

    /**
     * @return array<string, mixed>
     */
    private function form(Product $product, ProductVariant $low, ProductVariant $tall, string $tallStock): array
    {
        return [
            'form' => 'produkt-'.$product->id,
            'name' => 'Wazony',
            'category_id' => $this->vases->id,
            'is_published' => '1',
            'en' => ['name' => 'Vases'],
            'variants' => [
                ['id' => $low->id, 'label' => 'Niski 16 cm', 'label_en' => 'Low 16 cm', 'price' => '239', 'price_eur' => '59', 'stock' => '3'],
                ['id' => $tall->id, 'label' => 'Wysoki 24 cm', 'label_en' => 'Tall 24 cm', 'price' => '299', 'price_eur' => '74', 'stock' => $tallStock],
            ],
        ];
    }
}
