<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private Category $mugs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->mugs = Category::factory()->create(['name' => 'Kubki i filiżanki', 'slug' => 'kubki-i-filizanki', 'group' => CategoryGroup::Ceramics]);
        $mug = Product::factory()->create(['name' => 'Kubki malowane', 'slug' => 'kubki-malowane', 'category_id' => $this->mugs->id, 'is_published' => true]);
        ProductVariant::factory()->create(['product_id' => $mug->id, 'price_gross' => 7900, 'stock' => 2]);
    }

    public function test_guests_are_sent_to_the_login(): void
    {
        $this->get('/panel/kategorie')->assertRedirect('/panel/logowanie');
        $this->put('/panel/kategorie/'.$this->mugs->id)->assertRedirect('/panel/logowanie');
    }

    public function test_the_screen_lists_categories_with_counters_and_what_the_page_composes(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/panel/kategorie')
            ->assertOk()
            ->assertSee('Kubki i filiżanki')
            ->assertSee('Tytuł w Google')
            ->assertSee('<span x-text="length">0</span> / 60', false)
            ->assertSee('placeholder="Kubki i filiżanki — rękodzieło z Krakowa', false);
    }

    public function test_the_list_shows_what_a_shelf_lacks_and_save_and_next_opens_the_next_one(): void
    {
        $vases = Category::factory()->create(['name' => 'Wazony i patery', 'slug' => 'wazony-i-patery', 'group' => CategoryGroup::Ceramics]);
        $this->mugs->update(['seo_title' => 'Kubki z Krakowa', 'seo_description' => 'Kubki z gliny.']);
        $owner = User::factory()->create();

        $this->actingAs($owner)->get('/panel/kategorie')
            ->assertOk()
            ->assertSee('Półki w sklepie')
            ->assertSee('Google ✓')
            ->assertSee('Google: złoży sama')
            ->assertSee('Zapisz i następna →');

        $first = Category::query()->orderBy('sort_order')->orderBy('id')->first();
        $second = $first->is($vases) ? $this->mugs : $vases;

        $this->put('/panel/kategorie/'.$first->id, ['form' => 'kategoria-'.$first->id, 'intro' => 'Wstęp.', 'next' => $second->id])
            ->assertRedirect('/panel/kategorie#kategoria-'.$second->id)
            ->assertSessionHas('panel_status', 'Zapisane. Teraz: '.$second->name.'.');
        $this->assertSame('Wstęp.', $first->fresh()->intro);
    }

    public function test_texts_reach_the_shop_and_clearing_them_brings_back_the_composed_ones(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->put('/panel/kategorie/'.$this->mugs->id, [
            'form' => 'kategoria-'.$this->mugs->id,
            'seo_title' => 'Kubki ręcznie robione z Krakowa',
            'seo_description' => 'Kubki z gliny, każdy inny. Wysyłka w 3–5 dni.',
            'intro' => 'Każdy kubek toczę i maluję sama.',
        ])->assertRedirect('/panel/kategorie#kategoria-'.$this->mugs->id);

        $this->get('/sklep/kubki-i-filizanki')
            ->assertSee('<title>Kubki ręcznie robione z Krakowa</title>', false)
            ->assertSee('<meta name="description" content="Kubki z gliny, każdy inny. Wysyłka w 3–5 dni.">', false)
            ->assertSee('Każdy kubek toczę i maluję sama.');

        $this->actingAs($owner)->put('/panel/kategorie/'.$this->mugs->id, ['form' => 'kategoria-'.$this->mugs->id, 'seo_title' => '', 'seo_description' => ' ', 'intro' => '']);

        $this->assertSame([null, null, null], [$this->mugs->fresh()->seo_title, $this->mugs->fresh()->seo_description, $this->mugs->fresh()->intro]);
        $this->get('/sklep/kubki-i-filizanki')
            ->assertSee('<title>Kubki i filiżanki — rękodzieło z Krakowa', false)
            ->assertDontSee('Każdy kubek toczę i maluję sama.');
    }

    public function test_english_texts_are_saved_only_for_a_category_with_an_english_name(): void
    {
        $this->mugs->translations()->create(['locale' => 'en', 'slug' => 'mugs-and-cups', 'name' => 'Mugs and cups']);
        $owner = User::factory()->create();

        $this->actingAs($owner)->get('/panel/kategorie')->assertSee('Tytuł w Google po angielsku');
        $this->actingAs($owner)->put('/panel/kategorie/'.$this->mugs->id, [
            'form' => 'kategoria-'.$this->mugs->id,
            'en' => ['seo_title' => 'Handmade mugs from Kraków', 'intro' => 'Every mug is thrown and painted by me.'],
        ])->assertSessionHasNoErrors();

        $english = $this->mugs->translations()->where('locale', 'en')->first();
        $this->assertSame(['Handmade mugs from Kraków', null, 'Every mug is thrown and painted by me.'], [$english->seo_title, $english->seo_description, $english->intro]);
        $this->assertNull($this->mugs->fresh()->getRawOriginal('seo_title'));
    }

    public function test_texts_have_hard_limits(): void
    {
        $this->actingAs(User::factory()->create())
            ->put('/panel/kategorie/'.$this->mugs->id, [
                'form' => 'kategoria-'.$this->mugs->id,
                'seo_title' => str_repeat('a', 121),
                'seo_description' => str_repeat('a', 256),
            ])
            ->assertRedirect('/panel/kategorie#kategoria-'.$this->mugs->id)
            ->assertSessionHasErrorsIn('kategoria-'.$this->mugs->id, ['seo_title', 'seo_description']);
    }
}
