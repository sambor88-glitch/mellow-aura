<?php

namespace Tests\Feature\Checkout;

use App\Models\User;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Actions\AnonymizeCustomer;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Kasia's photo of one sold piece, added in the panel, opens the piece's page (MA-290).
 */
class PiecePhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->withoutVite();
    }

    public function test_a_piece_without_any_photo_opens_with_its_name_not_an_empty_frame(): void
    {
        $order = $this->paidOrder();

        foreach (['Wazon z paprocią', 'Kubek z napisem'] as $name) {
            $this->get('/k/'.$this->item($order, $name)->page_token)
                ->assertOk()
                ->assertDontSee('fetchpriority="high"', false)
                ->assertDontSee('aspect-[4/5]', false)
                ->assertDontSee('jeszcze się robi')
                ->assertSee('<h1', false);
        }
    }

    public function test_the_photo_of_the_piece_comes_before_the_photo_of_the_product(): void
    {
        $order = $this->paidOrder();
        $vase = $this->item($order, 'Wazon z paprocią');
        $shopPhoto = $vase->variant->product->addMedia(UploadedFile::fake()->image('sklep.jpg', 800, 1000))
            ->withCustomProperties(['alt' => 'Wazon z paprocią na lnianym obrusie'])->toMediaCollection('images');

        $this->get('/k/'.$vase->page_token)
            ->assertOk()
            ->assertSee('src="'.$shopPhoto->getUrl('card').'"', false)
            ->assertSee('alt="Wazon z paprocią na lnianym obrusie"', false);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.orders.piece-photo', [$order, $vase]), ['photo' => UploadedFile::fake()->image('IMG_5120.jpg', 1600, 2000)]);
        auth()->logout();
        $piecePhoto = $vase->fresh()->getFirstMedia('photo');

        $this->get('/k/'.$vase->page_token)
            ->assertOk()
            ->assertSee('src="'.$piecePhoto->getUrl('card').'"', false)
            ->assertSee($piecePhoto->getUrl('phone').' 720w', false)
            ->assertSee('alt="Wazon z paprocią — zdjęcie tej sztuki z pracowni"', false)
            ->assertDontSee($shopPhoto->getUrl('card'), false);
    }

    public function test_kasia_adds_replaces_and_removes_the_photo_of_a_mug_from_the_configurator(): void
    {
        $order = $this->paidOrder();
        $mug = $this->item($order, 'Kubek z napisem');
        $this->actingAs(User::factory()->create());

        $this->get('/panel/zamowienia/'.$order->number)
            ->assertOk()
            ->assertSee('action="'.route('admin.orders.piece-photo', [$order, $mug]).'"', false)
            ->assertSee('Dodaj zdjęcie sztuki: Kubek z napisem');

        $this->post(route('admin.orders.piece-photo', [$order, $mug]), ['photo' => UploadedFile::fake()->image('IMG_5120.jpg', 3200, 4000)])
            ->assertRedirect(route('admin.orders.show', $order).'#sztuka-'.$mug->id)
            ->assertSessionHas('panel_status', 'Zdjęcie dodane — widać je na stronie sztuki.');

        $photo = $mug->fresh()->getFirstMedia('photo');
        // Scaled down and saved again as WebP; named after the piece, never after the order or the buyer.
        $this->assertSame('image/webp', $photo->mime_type);
        $this->assertSame([1920, 2400], array_slice(getimagesize($photo->getPath()), 0, 2));
        $this->assertStringStartsWith('kubek-z-napisem-', $photo->file_name);
        $this->assertStringNotContainsString((string) $order->number, $photo->file_name);
        $this->assertTrue($photo->hasGeneratedConversion('thumb'));

        $this->get('/panel/zamowienia/'.$order->number)
            ->assertSee($photo->getUrl('thumb'), false)
            ->assertSee('Zmień zdjęcie sztuki: Kubek z napisem');
        $this->get('/k/'.$mug->page_token)->assertOk()->assertSee($photo->getUrl('card'), false);

        // One photo per piece: a new one takes the place of the old.
        $this->post(route('admin.orders.piece-photo', [$order, $mug]), ['photo' => UploadedFile::fake()->image('IMG_5121.png', 800, 1000)])
            ->assertSessionHas('panel_status', 'Zdjęcie zmienione — widać je na stronie sztuki.');
        $this->assertCount(1, $mug->fresh()->getMedia('photo'));
        Storage::disk('public')->assertMissing($photo->getPathRelativeToRoot());

        $this->delete(route('admin.orders.piece-photo.destroy', [$order, $mug]))
            ->assertRedirect(route('admin.orders.show', $order).'#sztuka-'.$mug->id);
        $this->assertFalse($mug->fresh()->hasMedia('photo'));
        $this->get('/k/'.$mug->page_token)->assertOk()->assertDontSee('fetchpriority="high"', false);
    }

    public function test_only_a_photo_up_to_ten_megabytes_is_taken(): void
    {
        $order = $this->paidOrder();
        $vase = $this->item($order, 'Wazon z paprocią');
        $this->actingAs(User::factory()->create());

        foreach ([
            [[], 'Wybierz zdjęcie z telefonu albo komputera'],
            [['photo' => UploadedFile::fake()->create('faktura.pdf', 100, 'application/pdf')], 'Tego pliku nie dodam — wybierz zdjęcie JPG, PNG albo WebP'],
            [['photo' => UploadedFile::fake()->image('duze.jpg')->size(11 * 1024)], 'Zdjęcie jest za duże — do 10 MB'],
        ] as [$data, $message]) {
            $this->post(route('admin.orders.piece-photo', [$order, $vase]), $data)
                ->assertRedirect(route('admin.orders.show', $order).'#sztuka-'.$vase->id)
                ->assertSessionHasErrors(['photo' => $message], null, 'piecePhoto'.$vase->id);
        }

        $this->assertFalse($vase->fresh()->hasMedia('photo'));
    }

    public function test_only_a_paid_piece_with_a_certificate_takes_a_photo(): void
    {
        $order = $this->paidOrder();
        $unpaid = $this->paidOrder(PaymentStatus::Pending);
        $photo = fn () => ['photo' => UploadedFile::fake()->image('a.jpg')];
        $this->actingAs(User::factory()->create());

        foreach (['Voucher kwotowy', 'Pakowanie na prezent'] as $name) {
            $this->post(route('admin.orders.piece-photo', [$order, $this->item($order, $name)]), $photo())->assertNotFound();
            $this->delete(route('admin.orders.piece-photo.destroy', [$order, $this->item($order, $name)]))->assertNotFound();
        }
        $this->post(route('admin.orders.piece-photo', [$unpaid, $this->item($unpaid, 'Wazon z paprocią')]), $photo())->assertNotFound();
        // An item of another order under this order's address.
        $this->post(route('admin.orders.piece-photo', [$order, $this->item($unpaid, 'Wazon z paprocią')]), $photo())->assertNotFound();
        $this->assertSame(0, OrderItem::query()->get()->sum(fn (OrderItem $item) => $item->getMedia('photo')->count()));

        $this->get('/panel/zamowienia/'.$order->number)
            ->assertDontSee(route('admin.orders.piece-photo', [$order, $this->item($order, 'Voucher kwotowy')]), false);

        auth()->logout();
        $this->post(route('admin.orders.piece-photo', [$order, $this->item($order, 'Wazon z paprocią')]), $photo())->assertRedirect('/panel/logowanie');
    }

    public function test_the_page_with_a_photo_still_says_nothing_about_the_buyer(): void
    {
        $order = $this->paidOrder();
        $vase = $this->item($order, 'Wazon z paprocią');
        $this->actingAs(User::factory()->create())
            ->post(route('admin.orders.piece-photo', [$order, $vase]), ['photo' => UploadedFile::fake()->image('anna-nowak.jpg', 800, 1000)]);
        auth()->logout();

        $response = $this->get('/k/'.$vase->page_token)->assertOk()->assertSee('fetchpriority="high"', false);

        foreach (['Anna Nowak', 'anna-nowak', 'ania@example.com', '600100200', $order->number, 'Długa 1', '31-001'] as $personal) {
            $response->assertDontSee($personal, false);
        }
    }

    public function test_forgetting_a_customer_removes_the_photo_of_her_inscription(): void
    {
        $order = $this->paidOrder();
        $mug = $this->item($order, 'Kubek z napisem');
        $vase = $this->item($order, 'Wazon z paprocią');
        $this->actingAs(User::factory()->create());
        foreach ([$mug, $vase] as $item) {
            $this->post(route('admin.orders.piece-photo', [$order, $item]), ['photo' => UploadedFile::fake()->image('a.jpg', 800, 1000)]);
        }

        app(AnonymizeCustomer::class)('ania@example.com');

        // The mug's photo shows her text; the vase is only clay and glaze, and its page stays as it was.
        $this->assertFalse($mug->fresh()->hasMedia('photo'));
        $this->assertTrue($vase->fresh()->hasMedia('photo'));
    }

    private function item(Order $order, string $name): OrderItem
    {
        return $order->items->firstWhere('product_name', $name);
    }

    private function paidOrder(PaymentStatus $payment = PaymentStatus::Paid): Order
    {
        $ceramics = Category::factory()->create(['group' => CategoryGroup::Ceramics]);
        $vouchers = Category::factory()->create(['group' => CategoryGroup::Workshops]);
        $vase = ProductVariant::factory()->for(Product::factory()->create(['category_id' => $ceramics->id]))->create(['label' => 'Niski 16 cm']);
        $voucher = ProductVariant::factory()->for(Product::factory()->create(['category_id' => $vouchers->id]))->create();

        $order = Order::create([
            'status' => $payment === PaymentStatus::Paid ? OrderStatus::InProgress : OrderStatus::New,
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'phone' => '600100200',
            'shipping_method' => 'courier',
            'shipping_address' => ['street' => 'Długa 1', 'postal_code' => '31-001', 'city' => 'Kraków'],
            'shipping_gross' => 1600,
            'total_gross' => 50000,
            'payment_method' => PaymentMethod::Blik,
            'payment_status' => $payment,
            'paid_at' => $payment === PaymentStatus::Paid ? now() : null,
        ]);
        $order->update(['number' => 'MA-2026-'.(1000 + $order->id)]);

        foreach ([
            ['product_variant_id' => $vase->id, 'product_name' => 'Wazon z paprocią', 'variant_label' => 'Niski 16 cm', 'quantity' => 1],
            ['product_variant_id' => $voucher->id, 'product_name' => 'Voucher kwotowy', 'variant_label' => '250 zł', 'quantity' => 1],
            ['product_variant_id' => null, 'product_name' => 'Pakowanie na prezent', 'variant_label' => '', 'quantity' => 1, 'is_made_to_order' => true],
            ['product_variant_id' => null, 'product_name' => 'Kubek z napisem', 'variant_label' => 'Duży', 'quantity' => 1, 'is_made_to_order' => true, 'custom_text' => 'Dla Ani'],
        ] as $item) {
            $order->items()->create(['unit_price_gross' => 10000, ...$item]);
        }

        return $order->load('items');
    }
}
