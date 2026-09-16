<?php

namespace Tests\Feature\Gifts;

use App\Models\User;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_page_lists_the_vouchers_on_sale_with_their_workshop_description(): void
    {
        Setting::create(['key' => 'voucher_validity_months', 'value' => 12]);
        Setting::create(['key' => 'text_vouchers_lead', 'value' => 'Prezent bez rozmiaru.']);
        Setting::create(['key' => 'text_voucher_how_to_use', 'value' => 'napisz do mnie i podaj numer vouchera.']);
        Setting::create(['key' => 'contact_phone', 'value' => '+48 600 100 200']);
        Setting::create(['key' => 'voucher_workshop_notes', 'value' => ['voucher-para' => ['expect' => 'Trzy godziny we dwoje.']]]);
        $workshops = Category::factory()->create(['slug' => 'vouchery', 'name' => 'Vouchery', 'group' => CategoryGroup::Workshops]);
        $couple = $this->product('Voucher — warsztat dla pary', 'voucher-para', $workshops, 39000);
        $amount = $this->product('Voucher kwotowy', 'voucher-kwotowy', $workshops, 15000);
        $draft = $this->product('Voucher na koło', 'voucher-kolo', $workshops, 30000, ['is_published' => false]);
        $vase = $this->product('Wazony', 'wazony', Category::factory()->create(), 23900);

        $this->get('/voucher-na-warsztaty-ceramiczne')
            ->assertOk()
            ->assertSee('<title>Voucher na warsztaty ceramiczne w Krakowie | MellowAura</title>', false)
            ->assertSee('<meta name="description" content="Voucher na lepienie z ręki, warsztat dla pary albo kwotowy. PDF z imieniem i dedykacją zaraz po opłaceniu, ważny rok, termin do wyboru.">', false)
            ->assertSee('<link rel="canonical" href="'.route('vouchers.index').'">', false)
            ->assertSeeInOrder(['prezent &middot; voucher', 'Voucher na warsztaty ceramiczne w Krakowie', 'Prezent bez rozmiaru.', 'ważny rok'], false)
            ->assertSeeInOrder(['href="'.route('product.show', $couple).'"', 'Voucher — warsztat dla pary', 'Trzy godziny we dwoje.', 'href="'.route('product.show', $amount).'"', 'Voucher kwotowy'], false)
            ->assertDontSee('Voucher na koło')
            ->assertDontSee(route('product.show', $vase))
            ->assertSeeInOrder(['Jak to działa', 'Wybierasz voucher', 'Dostajesz PDF', 'Obdarowana osoba wybiera termin', 'Napisz do mnie i podaj numer vouchera. Voucher jest ważny rok od zakupu.'])
            ->assertSeeInOrder(['href="'.route('workshops.index').'"', 'href="'.route('gifts.index').'"', 'href="https://wa.me/48600100200"'], false);
    }

    public function test_without_vouchers_on_sale_the_page_points_to_the_contact_form(): void
    {
        Setting::create(['key' => 'voucher_validity_months', 'value' => 6]);

        $this->get('/voucher-na-warsztaty-ceramiczne')
            ->assertOk()
            ->assertSee('ważny 6 miesięcy')
            ->assertSeeInOrder(['Vouchery wrócą niedługo', 'href="'.e(route('content.contact', ['temat' => 'Warsztaty i terminy'])).'"'], false)
            ->assertSee('Pisze do mnie z numerem vouchera i razem ustalamy termin. Voucher jest ważny 6 miesięcy od zakupu.');
    }

    public function test_the_footer_and_the_workshops_page_lead_here_and_kasia_edits_the_sentence(): void
    {
        $this->get('/warsztaty-ceramiczne-krakow')->assertSee('href="'.route('vouchers.index').'"', false);
        $this->get('/kontakt')->assertSeeInOrder(['href="'.route('vouchers.index').'"', 'Vouchery na warsztaty'], false);

        $this->actingAs(User::factory()->create())
            ->put('/panel/prezenty/vouchery', ['voucher_validity_months' => '12', 'voucher_recipient_name_max_chars' => '40', 'voucher_dedication_max_chars' => '180', 'text_vouchers_lead' => " Prezent bez rozmiaru.\r\nTermin wybiera obdarowana osoba. "])
            ->assertRedirect('/panel/prezenty#vouchery');

        $this->assertSame("Prezent bez rozmiaru.\nTermin wybiera obdarowana osoba.", Setting::find('text_vouchers_lead')->value);
        $this->get('/panel/prezenty')->assertSee('Zdanie pod nagłówkiem na stronie voucherów')->assertSee('Termin wybiera obdarowana osoba.</textarea>', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function product(string $name, string $slug, Category $category, int $price, array $overrides = []): Product
    {
        $product = Product::factory()->create(['name' => $name, 'slug' => $slug, 'category_id' => $category->id, ...$overrides]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'price_gross' => $price, 'stock' => null]);

        return $product;
    }
}
