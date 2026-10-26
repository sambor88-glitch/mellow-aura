<?php

namespace Tests\Feature\Gifts;

use App\Modules\Catalog\Database\Seeders\CatalogSeeder;
use App\Modules\Gifts\Database\Seeders\GiftsSeeder;
use App\Modules\Gifts\Models\Bundle;
use App\Modules\Gifts\Models\BundleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GiftsSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(CatalogSeeder::class);
    }

    public function test_it_seeds_the_three_sets_from_the_sizes_their_descriptions_name(): void
    {
        $this->seed(GiftsSeeder::class);

        $bundles = Bundle::query()->with('items.variant.product')->orderBy('sort_order')->get();

        $this->assertSame(['Kubek i scrunchie w jednym kolorze', 'Poranek we dwoje', 'Wieczorny rytuał'], $bundles->pluck('name')->all());
        $this->assertSame(
            ['Kubki malowane ręcznie · 300 ml', 'Scrunchies w 3 rozmiarach · Średnia'],
            $bundles[0]->items->map(fn (BundleItem $item) => $item->variant->product->name.' · '.$item->variant->label)->all(),
        );
        $this->assertSame([13300, 26900, 16000], $bundles->map(fn (Bundle $bundle) => $bundle->price())->all());
        $this->assertSame(3, Bundle::query()->live()->count());
    }

    public function test_running_it_again_adds_nothing_and_keeps_changes_from_the_panel(): void
    {
        $this->seed(GiftsSeeder::class);
        Bundle::query()->where('name', 'Poranek we dwoje')->update(['discount_percent' => 15, 'is_published' => false]);

        $this->seed(GiftsSeeder::class);

        $this->assertSame(3, Bundle::count());
        $this->assertSame(6, BundleItem::count());
        $this->assertSame(15, Bundle::query()->where('name', 'Poranek we dwoje')->value('discount_percent'));
    }
}
