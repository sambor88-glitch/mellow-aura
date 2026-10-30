<?php

namespace Tests\Feature\Content;

use App\Modules\Content\Support\PagePhotos;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MA-298: a place without a photo or a row without its text leaves no empty frame on the home page. */
class HomeEmptyStatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_without_photos_the_gifts_services_and_workshops_have_no_empty_frames(): void
    {
        $this->withoutPhotos(['home-gifts-1', 'home-gifts-2', 'home-scarf', 'home-imprint', 'home-workshops-1', 'home-workshops-2', 'home-workshops-3']);
        $this->workshops([['name' => 'Lepienie z ręki', 'price_gross' => 25000, 'unit_label' => 'os.']]);

        $this->get('/')
            ->assertOk()
            // The texts stay: the gifts card, both service cards and the workshops.
            ->assertSee('id="gifts-title"', false)
            ->assertSee('href="'.route('content.scarf').'"', false)
            ->assertSee('href="'.route('content.imprint').'"', false)
            ->assertSeeText('Lepienie z ręki')
            // The frames meant for the photos do not.
            ->assertDontSee('flex min-w-0 flex-[1_1_300px] gap-1.5 p-1.5', false)
            ->assertDontSee('h-[clamp(460px,72vh,700px)]', false)
            ->assertDontSee('h-[clamp(460px,70vh,680px)]', false)
            ->assertSee('bg-ink p-3.5', false);
    }

    public function test_with_their_photos_the_frames_are_back(): void
    {
        $this->workshops([['name' => 'Lepienie z ręki', 'price_gross' => 25000, 'unit_label' => 'os.']]);

        $this->get('/')
            ->assertOk()
            ->assertSee('flex min-w-0 flex-[1_1_300px] gap-1.5 p-1.5', false)
            ->assertSee('h-[clamp(460px,72vh,700px)]', false)
            ->assertSee('h-[clamp(460px,70vh,680px)]', false)
            ->assertDontSee('bg-ink p-3.5', false);
    }

    public function test_one_gift_photo_fills_the_column_instead_of_half_of_it(): void
    {
        $this->withoutPhotos(['home-gifts-2']);

        $this->get('/')
            ->assertOk()
            ->assertSee('flex min-w-0 flex-[1_1_300px] gap-1.5 p-1.5', false)
            ->assertSee('aspect-square min-w-0 flex-1 overflow-hidden', false)
            ->assertDontSee('aspect-square w-1/2', false);
    }

    public function test_a_workshop_without_a_unit_or_a_name_leaves_no_dangling_slash_or_empty_row(): void
    {
        $this->workshops([
            ['name' => 'Toczenie na kole', 'price_gross' => 25000],
            ['name' => '', 'summary' => 'Bez nazwy', 'price_gross' => 18000, 'unit_label' => 'os.'],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('od 250 zł</span>', false)
            ->assertDontSee('od 250 zł / ', false)
            ->assertDontSee('Bez nazwy')
            ->assertDontSee('od 180 zł');
    }

    public function test_workshops_without_any_name_leave_the_section_out(): void
    {
        $this->workshops([['name' => '', 'price_gross' => 25000, 'unit_label' => 'os.']]);

        $this->get('/')->assertOk()->assertDontSee('id="workshops-title"', false);
    }

    /** @param  list<string>  $places */
    private function withoutPhotos(array $places): void
    {
        Setting::query()->updateOrCreate(['key' => PagePhotos::SETTING], ['value' => collect($places)->mapWithKeys(fn (string $key) => [$key => ['path' => null]])->all()]);
    }

    /** @param  list<array<string, mixed>>  $types */
    private function workshops(array $types): void
    {
        Setting::query()->updateOrCreate(['key' => 'workshop_types'], ['value' => $types]);
    }
}
