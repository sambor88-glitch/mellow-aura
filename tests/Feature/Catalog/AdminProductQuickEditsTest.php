<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Mail\BackInStock;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\StockNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Changes made straight from the product list and the card (MA-307): the switch with „Cofnij”, the pieces on the shelf
 * with − and +, and „Duplikuj”.
 */
class AdminProductQuickEditsTest extends TestCase
{
    use RefreshDatabase;

    private Category $vases;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->vases = Category::factory()->create(['name' => 'Wazony i patery', 'slug' => 'wazony-i-patery', 'group' => CategoryGroup::Ceramics]);
    }

    private function vase(string $name = 'Wazony', int $sortOrder = 1): Product
    {
        $product = Product::factory()->create(['name' => $name, 'slug' => Str::slug($name), 'category_id' => $this->vases->id, 'sort_order' => $sortOrder, 'is_published' => true]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Niski 16 cm', 'price_gross' => 23900, 'stock' => 2]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Wysoki 24 cm', 'price_gross' => 32900, 'stock' => 0]);

        return $product->fresh('variants');
    }

    public function test_the_list_offers_the_stock_window_and_the_switch_without_leaving_it(): void
    {
        $vase = $this->vase();

        $this->actingAs(User::factory()->create())->get('/panel/produkty')
            ->assertOk()
            ->assertSee('data-variants="', false)
            ->assertSee('Sztuki na półce: Wazony, 2 szt. — zmień')
            ->assertSee('x-on:submit.prevent="toggle($el)"', false)
            ->assertSee('stockUrl', false)
            ->assertSee('__id__', false);

        // The switch answers the list's script in JSON; „Cofnij” sends the state back.
        $this->postJson('/panel/produkty/'.$vase->id.'/widocznosc')
            ->assertOk()
            ->assertJson(['published' => false, 'message' => 'Wazony — ukryte w sklepie']);
        $this->assertFalse($vase->fresh()->is_published);

        $this->postJson('/panel/produkty/'.$vase->id.'/widocznosc', ['published' => true])->assertJson(['published' => true]);
        $this->postJson('/panel/produkty/'.$vase->id.'/widocznosc', ['published' => true])->assertJson(['published' => true]);
        $this->assertTrue($vase->fresh()->is_published);

        // Without a script the form still reloads the list.
        $this->post('/panel/produkty/'.$vase->id.'/widocznosc')->assertRedirect('/panel/produkty#produkt-'.$vase->id);
    }

    public function test_pieces_save_at_once_tell_the_people_waiting_and_come_back_with_undo(): void
    {
        Mail::fake();
        $vase = $this->vase();
        [$low, $tall] = $vase->variants->all();
        StockNotification::create(['product_variant_id' => $tall->id, 'email' => 'ania@example.com', 'locale' => 'pl', 'token' => Str::random(40)]);

        $this->actingAs(User::factory()->create())
            ->putJson('/panel/produkty/'.$vase->id.'/sztuki', ['stock' => [$low->id => 5, $tall->id => 1]])
            ->assertOk()
            ->assertJson(['stock' => '6 szt.']);
        $this->assertSame([5, 1], [$low->fresh()->stock, $tall->fresh()->stock]);
        Mail::assertQueued(BackInStock::class, fn (BackInStock $mail) => $mail->hasTo('ania@example.com'));

        // „Cofnij” sends the numbers from before; empty means the pieces are not counted.
        $this->putJson('/panel/produkty/'.$vase->id.'/sztuki', ['stock' => [$low->id => 2, $tall->id => null]])->assertJson(['stock' => '2 szt.']);
        $this->assertSame([2, null], [$low->fresh()->stock, $tall->fresh()->stock]);
    }

    public function test_pieces_of_another_product_or_below_zero_are_refused(): void
    {
        $vase = $this->vase();
        $other = $this->vase('Patery', 2);
        $owner = User::factory()->create();

        $this->actingAs($owner)->putJson('/panel/produkty/'.$vase->id.'/sztuki', ['stock' => [$other->variants[0]->id => 9]])->assertStatus(422);
        $this->putJson('/panel/produkty/'.$vase->id.'/sztuki', ['stock' => [$vase->variants[0]->id => -1]])->assertStatus(422)->assertJsonValidationErrors('stock.'.$vase->variants[0]->id);
        $this->assertSame(2, $other->variants[0]->fresh()->stock);
        $this->assertSame(2, $vase->variants[0]->fresh()->stock);

        $this->post('/panel/wyloguj');
        $this->putJson('/panel/produkty/'.$vase->id.'/sztuki', ['stock' => [$vase->variants[0]->id => 9]])->assertUnauthorized();
    }

    public function test_duplicate_makes_a_hidden_copy_after_the_original_without_its_promotion_and_pieces(): void
    {
        Storage::fake('public');
        $first = $this->vase('Wazony', 1);
        $next = $this->vase('Patery', 2);
        $first->variants[0]->update(['compare_at_price' => 29900]);
        $first->addMedia(UploadedFile::fake()->image('wazon.jpg', 800, 1000))->withCustomProperties(['alt' => 'Wazon na stole'])->toMediaCollection('images');
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->post('/panel/produkty/'.$first->id.'/duplikuj');

        $copy = Product::query()->where('name', 'Wazony (kopia)')->sole();
        $response->assertRedirect('/panel/produkty/'.$copy->id.'?tab=desc')->assertSessionHas('duplicated', true);
        $this->assertFalse($copy->is_published);
        $this->assertSame('wazony-kopia', $copy->slug);
        $this->assertSame([1, 2, 3], [$first->fresh()->sort_order, $copy->sort_order, $next->fresh()->sort_order]);
        $this->assertSame(
            [['Niski 16 cm', 23900, null, 0], ['Wysoki 24 cm', 32900, null, 0]],
            $copy->variants()->orderBy('id')->get()->map(fn ($variant) => [$variant->label, $variant->price_gross, $variant->compare_at_price, $variant->stock])->all(),
        );
        $this->assertSame('Wazon na stole', $copy->getFirstMedia('images')->getCustomProperty('alt'));
        $this->assertSame(1, $first->fresh()->getMedia('images')->count());

        // The copy opens with its name selected.
        $this->get('/panel/produkty/'.$copy->id.'?tab=desc')->assertSee('name?.select()', false);
    }
}
