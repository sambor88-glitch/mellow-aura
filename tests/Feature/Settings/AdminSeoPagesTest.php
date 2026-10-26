<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Modules\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSeoPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_are_sent_to_the_login(): void
    {
        $this->get('/panel/google')->assertRedirect('/panel/logowanie');
        $this->put('/panel/google')->assertRedirect('/panel/logowanie');
    }

    public function test_the_screen_lists_fixed_pages_and_hints_what_a_visited_page_composes(): void
    {
        $composedTitle = $this->titleOf('/o-mnie');

        $this->actingAs(User::factory()->create())
            ->get('/panel/google')
            ->assertOk()
            ->assertSeeInOrder(['Strona główna', 'Sklep', 'O mnie', 'Kontakt'])
            ->assertSee('name="pages[shop__index][pl][title]"', false)
            ->assertSee('placeholder="'.e($composedTitle).'"', false)
            ->assertSee('<span x-text="length">0</span> / 60', false);
    }

    public function test_a_title_and_description_from_the_panel_replace_the_composed_ones_until_cleared(): void
    {
        $composedTitle = $this->titleOf('/sklep');
        $owner = User::factory()->create();

        $this->actingAs($owner)->put('/panel/google', ['pages' => ['shop__index' => ['pl' => [
            'title' => 'Ceramika ręcznie robiona — sklep z Krakowa',
            'description' => str_repeat('Kubki, talerze i wazony z mojej pracowni. ', 6),
        ]]]])->assertRedirect('/panel/google')->assertSessionHasNoErrors();

        $this->assertSame('Ceramika ręcznie robiona — sklep z Krakowa', app(Settings::class)->get('seo_pages')['shop.index']['pl']['title']);
        $html = $this->get('/sklep')->assertSee('<title>Ceramika ręcznie robiona — sklep z Krakowa</title>', false)->getContent();
        preg_match('/<meta name="description" content="([^"]*)">/', $html, $match);
        // Longer than Google shows, so the page cuts it at a word.
        $this->assertLessThanOrEqual(155, mb_strlen(html_entity_decode($match[1])));
        $this->assertStringEndsWith('…', html_entity_decode($match[1]));
        // Other pages keep their own titles.
        $this->assertNotSame('Ceramika ręcznie robiona — sklep z Krakowa', $this->titleOf('/o-mnie'));

        $this->actingAs($owner)->put('/panel/google', ['pages' => ['shop__index' => ['pl' => ['title' => '', 'description' => '']]]]);

        $this->assertSame([], app(Settings::class)->get('seo_pages', []));
        $this->assertSame($composedTitle, $this->titleOf('/sklep'));
    }

    public function test_english_is_kept_apart_and_unknown_pages_are_ignored(): void
    {
        $this->actingAs(User::factory()->create())->put('/panel/google', ['pages' => [
            'shop__index' => ['en' => ['title' => 'Handmade ceramics shop']],
            'admin__dashboard' => ['pl' => ['title' => 'Nie do zapisania']],
        ]])->assertSessionHasNoErrors();

        $this->assertSame(['shop.index' => ['en' => ['title' => 'Handmade ceramics shop', 'description' => null]]], app(Settings::class)->get('seo_pages'));
        $this->assertSame('Handmade ceramics shop', $this->titleOf('/en/shop'));
        $this->assertNotSame('Handmade ceramics shop', $this->titleOf('/sklep'));
    }

    public function test_titles_and_descriptions_have_hard_limits(): void
    {
        $this->actingAs(User::factory()->create())
            ->put('/panel/google', ['pages' => ['home' => ['pl' => ['title' => str_repeat('a', 121), 'description' => str_repeat('a', 256)]]]])
            ->assertRedirect('/panel/google')
            ->assertSessionHasErrors(['pages.home.pl.title', 'pages.home.pl.description']);
    }

    private function titleOf(string $url): string
    {
        preg_match('#<title>(.*?)</title>#s', $this->get($url)->assertOk()->getContent(), $match);

        return html_entity_decode($match[1]);
    }
}
