<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\GlbFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * „Zobacz u siebie” (MA-310): a 3D model added in the panel puts a button on the product page,
 * and its file goes out as a binary glTF file.
 */
class ProductArModelTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->withoutVite();
        $this->owner = User::factory()->create();
        $this->product = Product::factory()->create(['name' => 'Kubek Mleczna Droga', 'slug' => 'kubek-mleczna-droga']);
        ProductVariant::factory()->create(['product_id' => $this->product->id]);
    }

    public function test_a_product_without_a_model_has_no_button(): void
    {
        $this->get('/produkt/kubek-mleczna-droga')->assertOk()->assertDontSee('Zobacz u siebie');
        $this->actingAs($this->owner)->get('/panel/produkty')->assertOk()->assertSee('Model 3D — jeszcze żadnego');
    }

    public function test_a_model_added_in_the_panel_shows_on_the_product_page_and_is_sent_as_gltf(): void
    {
        $this->actingAs($this->owner)
            ->post($this->modelUrl(), ['model' => $this->glb('Scan 2026-10-31.glb')])
            ->assertRedirect('/panel/produkty#produkt-'.$this->product->id)
            ->assertSessionHas('panel_status', 'Model 3D dodany');

        $model = $this->product->fresh()->arModel();

        $this->assertNotNull($model);
        $this->assertStringStartsWith('kubek-mleczna-droga-', $model->file_name);
        $this->assertStringEndsWith('.glb', $model->file_name);
        $this->assertSame(GlbFile::MIME_TYPE, $model->mime_type);
        // A model is not a photo: no thumbnails made from it.
        $this->assertSame([], $model->generated_conversions->filter()->all());

        $this->get('/panel/produkty')->assertOk()->assertSee('Model 3D — klientki zobaczą go u siebie')->assertSee($model->file_name);

        $url = route('product.model', [$model, $model->file_name]);
        $this->get('/produkt/kubek-mleczna-droga')
            ->assertOk()
            ->assertSee('Zobacz u siebie')
            ->assertSee('arViewer(', false)
            ->assertSee(str_replace('/', '\/', $url), false);

        auth()->logout();
        $response = $this->get($url)->assertOk();
        $this->assertSame(GlbFile::MIME_TYPE, $response->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('glTF', $response->streamedContent());
    }

    public function test_a_new_model_replaces_the_old_one_and_can_be_deleted(): void
    {
        $this->actingAs($this->owner)->post($this->modelUrl(), ['model' => $this->glb('first.glb')]);
        $first = $this->product->fresh()->arModel();

        $this->post($this->modelUrl(), ['model' => $this->glb('second.glb')])
            ->assertSessionHas('panel_status', 'Model 3D podmieniony');

        $this->assertCount(1, $this->product->fresh()->getMedia(Product::MODEL_COLLECTION));
        $this->get(route('product.model', [$first, $first->file_name]))->assertNotFound();

        $this->delete($this->modelUrl())
            ->assertRedirect('/panel/produkty#produkt-'.$this->product->id)
            ->assertSessionHas('panel_status', 'Model 3D usunięty');

        $this->assertNull($this->product->fresh()->arModel());
        $this->get('/produkt/kubek-mleczna-droga')->assertOk()->assertDontSee('Zobacz u siebie');
    }

    public function test_only_a_real_glb_file_is_accepted(): void
    {
        $this->actingAs($this->owner);

        // A photo renamed to .glb, a glTF file of the wrong length, and no file at all.
        $renamed = UploadedFile::fake()->createWithContent('kubek.glb', (string) file_get_contents(UploadedFile::fake()->image('kubek.jpg')->getRealPath()));
        $cut = UploadedFile::fake()->createWithContent('kubek.glb', substr($this->glbBytes(), 0, -4));
        $wrongName = UploadedFile::fake()->createWithContent('kubek.obj', $this->glbBytes());

        foreach ([$renamed, $cut, $wrongName] as $file) {
            $this->post($this->modelUrl(), ['model' => $file])
                ->assertRedirect('/panel/produkty#produkt-'.$this->product->id)
                ->assertSessionHasErrors(['model' => 'Tego pliku nie dodam — wybierz model 3D w formacie GLB'], errorBag: 'model-'.$this->product->id);
        }

        $this->post($this->modelUrl())->assertSessionHasErrors(['model' => 'Wybierz plik z modelem 3D (GLB)'], errorBag: 'model-'.$this->product->id);
        $this->assertNull($this->product->fresh()->arModel());
    }

    public function test_the_model_file_is_found_only_for_a_published_product_and_its_own_name(): void
    {
        $this->actingAs($this->owner)->post($this->modelUrl(), ['model' => $this->glb('kubek.glb')]);
        $model = $this->product->fresh()->arModel();
        $photo = $this->product->addMedia(UploadedFile::fake()->image('kubek.jpg'))->toMediaCollection('images');

        $this->get('/modele-3d/'.$model->id.'/inna-nazwa.glb')->assertNotFound();
        $this->get('/modele-3d/'.$photo->id.'/'.$photo->file_name)->assertNotFound();

        $this->product->update(['is_published' => false]);
        $this->get(route('product.model', [$model, $model->file_name]))->assertNotFound();
    }

    public function test_the_model_file_is_open_on_staging_without_the_password(): void
    {
        $this->actingAs($this->owner)->post($this->modelUrl(), ['model' => $this->glb('kubek.glb')]);
        $model = $this->product->fresh()->arModel();
        auth()->logout();
        config(['staging.password' => 'tajne-haslo-stagingu']);

        $this->get('/produkt/kubek-mleczna-droga')->assertUnauthorized();
        $this->get(route('product.model', [$model, $model->file_name]))->assertOk();
    }

    public function test_only_the_owner_manages_models(): void
    {
        $this->post($this->modelUrl(), ['model' => $this->glb('kubek.glb')])->assertRedirect('/panel/logowanie');
        $this->assertNull($this->product->fresh()->arModel());
    }

    private function modelUrl(): string
    {
        return '/panel/produkty/'.$this->product->id.'/model-3d';
    }

    private function glb(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->glbBytes());
    }

    /** The smallest valid GLB: the header and one JSON chunk with an empty asset description. */
    private function glbBytes(): string
    {
        $json = str_pad('{"asset":{"version":"2.0"}}', 28, ' ');
        $chunk = pack('V', strlen($json)).'JSON'.$json;

        return 'glTF'.pack('V', 2).pack('V', 12 + strlen($chunk)).$chunk;
    }
}
