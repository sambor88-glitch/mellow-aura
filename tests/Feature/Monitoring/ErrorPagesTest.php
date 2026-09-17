<?php

namespace Tests\Feature\Monitoring;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Route::middleware('web')->group(function () {
            Route::match(['get', 'post'], '/test-bledow/usterka', fn () => throw new RuntimeException('Tabela orders nie istnieje'));
            Route::get('/test-bledow/{status}', fn (int $status) => abort($status));
        });
    }

    public function test_an_address_that_does_not_exist_gets_the_sites_page_with_a_way_back(): void
    {
        $this->get('/nie-ma-takiej-strony')
            ->assertNotFound()
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSee('Tej strony tu nie ma')
            ->assertSee('<a href="'.url('/sklep').'" class="button button-primary">Przejdź do sklepu</a>', false);
    }

    public function test_an_error_tells_the_customer_the_cart_is_safe_and_needs_no_database(): void
    {
        config(['app.debug' => false]);
        Exceptions::fake();
        DB::enableQueryLog();

        $this->get('/test-bledow/usterka')
            ->assertStatus(500)
            ->assertSee('Coś się zacięło po mojej stronie')
            ->assertSee('Twój koszyk jest bezpieczny — spróbuj za chwilę.')
            ->assertSee('Spróbuj jeszcze raz')
            ->assertDontSee('Tabela orders nie istnieje');

        $this->assertSame([], DB::getQueryLog());
        Exceptions::assertReported(RuntimeException::class);

        // After a form there is nothing to reload, only the way back.
        $this->post('/test-bledow/usterka')->assertStatus(500)->assertDontSee('Spróbuj jeszcze raz')->assertSee('Strona główna');
    }

    public function test_the_common_cases_explain_what_to_do(): void
    {
        $this->get('/test-bledow/419')->assertStatus(419)->assertSee('Ta strona była otwarta zbyt długo')->assertSee('Wróć do formularza');
        $this->get('/test-bledow/429')->assertStatus(429)->assertSee('Za dużo prób w krótkim czasie');
        $this->get('/test-bledow/503')->assertStatus(503)->assertSee('Robię porządki na stronie');
    }

    public function test_every_other_code_gets_the_sites_page_instead_of_the_frameworks(): void
    {
        foreach ([401, 402, 403, 405, 410] as $status) {
            $this->get('/test-bledow/'.$status)->assertStatus($status)->assertSee('Tej strony nie da się otworzyć');
        }

        foreach ([500, 502, 504] as $status) {
            $this->get('/test-bledow/'.$status)->assertStatus($status)->assertSee('Coś się zacięło po mojej stronie');
        }
    }
}
