<?php

namespace Tests\Feature\Content;

use App\Models\User;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminContentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
        $this->owner = User::factory()->create();
    }

    public function test_guests_are_sent_to_the_login(): void
    {
        $this->get('/panel/tresci')->assertRedirect('/panel/logowanie');
        $this->put('/panel/tresci/teksty')->assertRedirect('/panel/logowanie');
        $this->put('/panel/tresci/czeste-pytania')->assertRedirect('/panel/logowanie');
    }

    public function test_the_page_shows_every_text_list_and_a_link_to_each_page(): void
    {
        $this->actingAs($this->owner)
            ->get('/panel/tresci')
            ->assertOk()
            ->assertSee('>Treści</a>', false)
            ->assertSeeInOrder(['Strona główna', 'Lubię łączyć surowość z miękkością', 'O mnie', 'href="'.route('content.about').'"', 'Tworzę MellowAura', 'Pracownia', 'Warsztaty, które uczą', 'Kontakt', 'Odpisuję zwykle tego samego dnia', 'Hasło pod logo', 'A little bit of magic'], false)
            ->assertSeeInOrder(['Częste pytania', 'value="Jak szybko wyślesz zamówienie?"', 'Rzeczy, które są na stanie', 'value="Wysyłasz za granicę?"', 'placeholder="Nowe pytanie"'], false)
            ->assertSeeInOrder(['Pracownia — dobrze wiedzieć', 'value="Do 6 osób"', 'value="Przy jednym stole, bez tłoku."'], false)
            ->assertSeeInOrder(['Sprawy w formularzu kontaktowym', 'value="Zamówienie ze sklepu"', 'value="Współpraca albo prasa"'], false)
            ->assertSee('aria-label="Przesuń niżej: Jak szybko wyślesz zamówienie?"', false)
            ->assertDontSee('aria-label="Przesuń wyżej: Jak szybko wyślesz zamówienie?"', false);
    }

    public function test_texts_are_saved_and_an_emptied_one_disappears_from_the_page(): void
    {
        $this->actingAs($this->owner)
            ->put('/panel/tresci/teksty', [
                'text_about_paragraph_1' => "  Lepię z gliny od dziesięciu lat.\r\nI nadal się uczę.  ",
                'text_about_paragraph_2' => '',
                'text_contact_lead' => 'Odpisuję wieczorem.',
                'text_footer_tagline' => 'Ceramika z Krakowa.',
            ])
            ->assertRedirect('/panel/tresci#teksty')
            ->assertSessionHas('panel_status', 'Teksty zapisane. Klienci już je widzą.');

        $this->assertSame("Lepię z gliny od dziesięciu lat.\nI nadal się uczę.", Setting::find('text_about_paragraph_1')->value);
        $this->assertNull(Setting::find('text_about_paragraph_2')->value);

        app(Settings::class)->refresh();
        $this->get('/o-mnie')->assertSee('Lepię z gliny od dziesięciu lat.')->assertDontSee('Zajmuję się ceramiką i rękodziełem tekstylnym');
        $this->get('/kontakt')->assertSee('Odpisuję wieczorem.')->assertSee('Ceramika z Krakowa.');
    }

    public function test_a_text_too_long_is_explained_next_to_its_field(): void
    {
        $this->actingAs($this->owner)
            ->followingRedirects()
            ->put('/panel/tresci/teksty', ['text_contact_lead' => str_repeat('a', 301)])
            ->assertSee('Popraw zaznaczone pola, żeby zapisać.')
            ->assertSee('Ten tekst zmieszczę do 300 znaków — skróć go o kilka słów');

        $this->assertSame('Odpisuję zwykle tego samego dnia, chyba że stoję przy piecu. Wtedy wieczorem.', Setting::find('text_contact_lead')->value);
    }

    public function test_questions_are_added_removed_and_saved_in_order(): void
    {
        $this->actingAs($this->owner)
            ->put('/panel/tresci/czeste-pytania', ['faq' => [
                ['question' => 'Czy robisz talerze?', 'answer' => 'Tak, na zamówienie.'],
                ['question' => 'Stare pytanie', 'answer' => 'Do usunięcia.', 'remove' => '1'],
                ['question' => 'Pytanie na później', 'answer' => ''],
                ['question' => '', 'answer' => ''],
            ]])
            ->assertRedirect('/panel/tresci#czeste-pytania')
            ->assertSessionHas('panel_status', 'Częste pytania zapisane');

        // MySQL keeps JSON object keys in its own order, so the rows compare without it.
        $this->assertEquals([
            ['question' => 'Czy robisz talerze?', 'answer' => 'Tak, na zamówienie.'],
            ['question' => 'Pytanie na później', 'answer' => null],
        ], Setting::find('faq_items')->value);

        app(Settings::class)->refresh();
        $this->get('/wysylka-i-pielegnacja')->assertSee('Czy robisz talerze?')->assertDontSee('Pytanie na później')->assertDontSee('Stare pytanie');
    }

    public function test_an_arrow_moves_a_question_and_keeps_what_was_typed(): void
    {
        $this->actingAs($this->owner)
            ->put('/panel/tresci/czeste-pytania', [
                'move' => '1:up',
                'faq' => [
                    ['question' => 'Pierwsze', 'answer' => 'A'],
                    ['question' => 'Drugie — poprawione', 'answer' => 'B'],
                    ['question' => 'Trzecie', 'answer' => 'C'],
                ],
            ])
            ->assertSessionHas('panel_status', 'Kolejność zmieniona. Klienci już ją widzą.');

        $this->assertSame(['Drugie — poprawione', 'Pierwsze', 'Trzecie'], array_column(Setting::find('faq_items')->value, 'question'));

        $this->put('/panel/tresci/czeste-pytania', ['move' => '2:down', 'faq' => [['question' => 'A', 'answer' => '1'], ['question' => 'B', 'answer' => '2'], ['question' => 'C', 'answer' => '3']]]);
        $this->assertSame(['A', 'B', 'C'], array_column(Setting::find('faq_items')->value, 'question'));
    }

    public function test_an_answer_without_a_question_is_explained_on_its_row(): void
    {
        $this->actingAs($this->owner)
            ->followingRedirects()
            ->put('/panel/tresci/czeste-pytania', ['faq' => [
                ['question' => 'Czy robisz talerze?', 'answer' => 'Tak.'],
                ['question' => '', 'answer' => 'Odpowiedź bez pytania'],
            ]])
            ->assertSee('Wpisz pytanie do tej odpowiedzi')
            ->assertSee('Odpowiedź bez pytania');

        $this->assertSame('Jak szybko wyślesz zamówienie?', Setting::find('faq_items')->value[0]['question']);
    }

    public function test_studio_facts_and_contact_topics_are_saved(): void
    {
        $this->actingAs($this->owner)
            ->put('/panel/tresci/pracownia', ['facts' => [
                ['title' => 'Do 4 osób', 'text' => 'Mniejszy stół.'],
                ['title' => 'Od 6 lat', 'text' => '', 'remove' => '1'],
                ['title' => 'Herbata w cenie', 'text' => ''],
            ]])
            ->assertRedirect('/panel/tresci#pracownia')
            ->assertSessionHas('panel_status', 'Fakty o pracowni zapisane');

        $this->assertEquals([['title' => 'Do 4 osób', 'text' => 'Mniejszy stół.'], ['title' => 'Herbata w cenie', 'text' => null]], Setting::find('studio_facts')->value);

        $this->put('/panel/tresci/kontakt', ['topics' => [['label' => 'Warsztaty'], ['label' => ' Zamówienie ze sklepu '], ['label' => '']]])
            ->assertRedirect('/panel/tresci#kontakt')
            ->assertSessionHas('panel_status', 'Sprawy w formularzu zapisane');

        $this->assertSame(['Warsztaty', 'Zamówienie ze sklepu'], Setting::find('contact_form_topics')->value);

        $this->put('/panel/tresci/kontakt', ['topics' => [['label' => 'Warsztaty'], ['label' => 'warsztaty']]])
            ->assertSessionHasErrorsIn('kontakt', ['topics.1.label' => 'Ta sprawa jest już na liście']);

        app(Settings::class)->refresh();
        $this->get('/pracownia')->assertSee('Do 4 osób')->assertSee('Herbata w cenie')->assertDontSee('Od 6 lat');
        $this->get('/kontakt')->assertSeeInOrder(['W jakiej sprawie?', 'Warsztaty', 'Zamówienie ze sklepu'])->assertDontSee('Współpraca albo prasa');
    }
}
