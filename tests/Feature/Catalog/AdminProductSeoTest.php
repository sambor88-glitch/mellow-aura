<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductSeoTest extends TestCase
{
    use RefreshDatabase;

    private Product $mug;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $category = Category::factory()->create(['name' => 'Kubki i filiżanki', 'slug' => 'kubki-i-filizanki', 'group' => CategoryGroup::Ceramics]);
        $this->mug = Product::factory()->create([
            'name' => 'Kubki malowane',
            'slug' => 'kubki-malowane',
            'category_id' => $category->id,
            'description' => str_repeat('Maluję każdy kubek ręcznie pędzelkiem z włosia, ', 5).'koniec.',
            'is_published' => true,
        ]);
        $this->variant = ProductVariant::factory()->create(['product_id' => $this->mug->id, 'price_gross' => 7900, 'stock' => 2]);
    }

    public function test_the_google_description_replaces_the_composed_one_until_it_is_cleared(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->put('/panel/produkty/'.$this->mug->id, $this->form('Kubek malowany ręcznie w Krakowie — każdy inny.'))
            ->assertRedirect('/panel/produkty#produkt-'.$this->mug->id);

        $this->assertSame('Kubek malowany ręcznie w Krakowie — każdy inny.', $this->mug->fresh()->seo_description);
        $this->get('/produkt/kubki-malowane')
            ->assertSee('<meta name="description" content="Kubek malowany ręcznie w Krakowie — każdy inny.">', false);

        $this->actingAs($owner)->put('/panel/produkty/'.$this->mug->id, $this->form(''));

        $this->assertNull($this->mug->fresh()->seo_description);
        $composed = $this->get('/produkt/kubki-malowane')->getContent();
        preg_match('/<meta name="description" content="([^"]*)">/', $composed, $match);
        $this->assertLessThanOrEqual(155, mb_strlen(html_entity_decode($match[1])));
        $this->assertStringStartsWith('Maluję każdy kubek', html_entity_decode($match[1]));
        $this->assertStringEndsWith('…', html_entity_decode($match[1]));
    }

    public function test_the_form_counts_characters_and_shows_what_the_page_composes(): void
    {
        $this->mug->update(['seo_description' => str_repeat('a', 160)]);

        $this->actingAs(User::factory()->create())
            ->get('/panel/produkty')
            ->assertOk()
            ->assertSee('Opis dla Google')
            ->assertSee('Google utnie po 155 znakach')
            ->assertSee('<span x-text="length">160</span> / 155', false)
            ->assertSee('placeholder="Maluję każdy kubek', false);
    }

    public function test_the_english_google_description_is_saved_with_the_english_version(): void
    {
        $this->actingAs(User::factory()->create())->put('/panel/produkty/'.$this->mug->id, [
            ...$this->form(''),
            'en' => ['name' => 'Painted mugs', 'description' => 'Painted by hand.', 'seo_description' => 'Hand-painted mug from Kraków.'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Hand-painted mug from Kraków.', $this->mug->translation('en')->seo_description);
        $this->assertNull($this->mug->fresh()->getRawOriginal('seo_description'));
    }

    public function test_the_google_description_has_a_hard_limit(): void
    {
        $this->actingAs(User::factory()->create())
            ->put('/panel/produkty/'.$this->mug->id, $this->form(str_repeat('a', 256)))
            ->assertSessionHasErrorsIn('produkt-'.$this->mug->id, 'seo_description');
    }

    /**
     * @return array<string, mixed>
     */
    private function form(string $seoDescription): array
    {
        return [
            'form' => 'produkt-'.$this->mug->id,
            'name' => 'Kubki malowane',
            'category_id' => $this->mug->category_id,
            'description' => $this->mug->description,
            'seo_description' => $seoDescription,
            'is_published' => '1',
            'variants' => [['id' => $this->variant->id, 'label' => '', 'price' => '79', 'stock' => '2']],
        ];
    }
}
