<?php

namespace App\Modules\Content\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Content\Http\Requests\Admin\SaveB2bFactsRequest;
use App\Modules\Content\Http\Requests\Admin\SaveContactTopicsRequest;
use App\Modules\Content\Http\Requests\Admin\SaveCustomOrderStepsRequest;
use App\Modules\Content\Http\Requests\Admin\SaveFaqRequest;
use App\Modules\Content\Http\Requests\Admin\SavePageTextsRequest;
use App\Modules\Content\Http\Requests\Admin\SaveStudioFactsRequest;
use App\Modules\Content\Models\InstagramPost;
use App\Modules\Settings\Actions\SaveSettings;
use App\Modules\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The panel's „Treści”: every sentence on the content pages, the questions and answers, the studio's facts,
 * the custom order steps, the cards for cafés and the contact form's topics. Each card saves on its own
 * and keeps its own mistakes.
 */
class ContentController extends Controller
{
    public function edit(Settings $settings): View
    {
        $rows = fn (string $key, array $fields) => collect((array) $settings->get($key, []))
            ->filter(fn (mixed $row) => is_array($row))
            ->map(fn (array $row) => collect($fields)->mapWithKeys(fn (string $field) => [$field => $row[$field] ?? null])->all())
            ->values()
            ->all();

        return view('content::admin.edit', [
            'texts' => collect(SavePageTextsRequest::textKeys())->mapWithKeys(fn (string $key) => [$key => $settings->get($key)])->all(),
            'questions' => $rows('faq_items', ['question', 'answer']),
            'facts' => $rows('studio_facts', ['title', 'text']),
            'customOrderSteps' => $rows('custom_order_steps', ['title', 'text']),
            'b2bFacts' => $rows('b2b_facts', ['title', 'text']),
            'instagram' => [
                'instagram_feed_url' => $settings->get('instagram_feed_url'),
                'instagram_feed_hashtag' => $settings->get('instagram_feed_hashtag'),
                'posts' => InstagramPost::query()->latest('posted_at')->get(),
            ],
            'topics' => collect((array) $settings->get('contact_form_topics', []))
                ->filter(fn (mixed $topic) => is_string($topic) && $topic !== '')
                ->map(fn (string $topic) => ['label' => $topic])
                ->values()
                ->all(),
        ]);
    }

    public function texts(SavePageTextsRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return $this->saved('teksty', 'Teksty zapisane. Klienci już je widzą.');
    }

    public function faq(SaveFaqRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return $this->saved('czeste-pytania', $request->moved() ? 'Kolejność zmieniona. Klienci już ją widzą.' : 'Częste pytania zapisane');
    }

    public function facts(SaveStudioFactsRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return $this->saved('pracownia', $request->moved() ? 'Kolejność zmieniona. Klienci już ją widzą.' : 'Fakty o pracowni zapisane');
    }

    public function customOrderSteps(SaveCustomOrderStepsRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return $this->saved('zamowienia-indywidualne', $request->moved() ? 'Kolejność zmieniona. Klienci już ją widzą.' : 'Kroki zamówienia zapisane');
    }

    public function b2bFacts(SaveB2bFactsRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return $this->saved('gastronomia', $request->moved() ? 'Kolejność zmieniona. Klienci już ją widzą.' : 'Karty dla lokali zapisane');
    }

    public function topics(SaveContactTopicsRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return $this->saved('kontakt', $request->moved() ? 'Kolejność zmieniona. Klienci już ją widzą.' : 'Sprawy w formularzu zapisane');
    }

    private function saved(string $card, string $status): RedirectResponse
    {
        return to_route('admin.content.edit')->withFragment($card)->with('panel_status', $status);
    }
}
