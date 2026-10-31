<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

/**
 * Tabs on long panel screens (MA-315): the server opens the asked tab, then the one in the address, then the first;
 * a tab with a mistake is marked for screen readers too. Switching tabs happens in the browser (panel-tabs.js).
 */
class PanelTabsTest extends TestCase
{
    private const VIEW = <<<'BLADE'
        <x-admin::tabs :tabs="$tabs" label="Części ekranu" :active="$active">
            <x-admin::tab-panel name="delivery" class="grid">Dostawa i opłaty</x-admin::tab-panel>
            <x-admin::tab-panel name="studio">Pracownia</x-admin::tab-panel>
        </x-admin::tabs>
        BLADE;

    private function render(?string $query = null, ?string $active = null, array $tabs = ['delivery' => 'Dostawa', 'studio' => 'Pracownia']): string
    {
        if ($query !== null) {
            $this->app['request']->query->set('tab', $query);
        }

        return (string) $this->blade(self::VIEW, ['tabs' => $tabs, 'active' => $active]);
    }

    public function test_the_first_tab_opens_without_anything_asked(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('role="tablist" aria-label="Części ekranu"', $html);
        $this->assertStringContainsString("panelTabs('delivery', JSON.parse(", $html);
        $this->assertMatchesRegularExpression('/id="tab-delivery"[^>]*aria-selected="true"/', $html);
        $this->assertMatchesRegularExpression('/id="tab-studio"[^>]*aria-selected="false"/', $html);
        // The open panel shows; the other waits hidden until its tab is chosen.
        $this->assertMatchesRegularExpression('/data-tab-panel="delivery"\s+x-show="tab === \'delivery\'"\s+class="grid"/', $html);
        $this->assertMatchesRegularExpression('/data-tab-panel="studio"\s+x-show="tab === \'studio\'"\s+style="display: none"/', $html);
    }

    public function test_the_address_opens_its_tab_and_an_unknown_one_is_ignored(): void
    {
        $this->assertStringContainsString("panelTabs('studio'", $this->render(query: 'studio'));
        $this->assertStringContainsString("panelTabs('delivery'", $this->render(query: 'nowhere'));
    }

    public function test_the_screen_opens_the_tab_with_a_mistake_and_marks_it(): void
    {
        $html = $this->render(query: 'delivery', active: 'studio', tabs: [
            'delivery' => 'Dostawa',
            'studio' => ['label' => 'Pracownia', 'dot' => 'error', 'count' => 3],
        ]);

        $this->assertStringContainsString("panelTabs('studio'", $html);
        $this->assertStringContainsString(' — są błędy', $html);
        $this->assertStringContainsString('<span class="text-[11.5px] text-label tabular-nums">3</span>', $html);
        $this->assertMatchesRegularExpression('/data-tab-panel="delivery"\s+x-show="tab === \'delivery\'"\s+style="display: none"/', $html);
    }
}
