{{-- The search window (MA-308, resources/js/panel-search.js), from the mock-up of MA-306, screen 4. A window in the middle on
     a computer, the whole screen on a phone. Every result is a link; Enter clicks the one chosen with the arrows. --}}
<div x-ref="overlay" x-show="open" x-cloak x-on:mousedown.self="hide()" x-on:keydown.escape.prevent.stop="hide()"
     class="fixed inset-0 z-[300] flex animate-ma-in items-start justify-center bg-scrim sm:px-4 sm:pt-[max(9vh,24px)] sm:pb-6">
    <div role="dialog" aria-modal="true" aria-label="Szukaj w panelu" x-trap.noscroll.noreturn="open"
         class="flex h-full w-full flex-col overflow-hidden bg-cream sm:h-auto sm:max-h-[min(600px,100%)] sm:w-[min(660px,100%)] sm:animate-ma-up-quick sm:rounded-[24px] sm:border sm:border-line sm:shadow-float">
        <div class="flex items-center gap-3 border-b border-divider py-1.5 pr-3.5 pl-5 text-label max-sm:pt-[max(6px,env(safe-area-inset-top))]">
            <svg viewBox="0 0 24 24" aria-hidden="true" class="size-[18px] flex-none fill-none stroke-current stroke-[1.6] [stroke-linecap:round]"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5 20 20"/></svg>
            <input x-ref="input" x-model="term" type="search" role="combobox" aria-label="Czego szukasz?" aria-expanded="true" aria-controls="szukaj-wyniki" aria-autocomplete="list"
                   x-bind:aria-activedescendant="activeId()" autocomplete="off" autocapitalize="off" spellcheck="false" enterkeyhint="go" maxlength="80"
                   placeholder="{{ $owner ? 'Szukaj produktu, zamówienia, klienta, ustawienia…' : 'Szukaj zamówienia — numer, imię, e-mail' }}"
                   x-on:keydown.arrow-down.prevent="move(1)" x-on:keydown.arrow-up.prevent="move(-1)" x-on:keydown.enter.prevent="pick()"
                   class="min-w-0 flex-1 border-0 bg-transparent py-3.5 text-[17px] text-ink outline-none placeholder:text-hint [&::-webkit-search-cancel-button]:appearance-none">
            <span aria-hidden="true" class="flex-none rounded-full border border-line bg-linen px-2.5 py-0.5 text-[11px] tracking-[0.04em] text-label max-sm:hidden">Esc</span>
            <button type="button" x-on:click="hide()" class="min-h-11 flex-none px-1 text-[13.5px] text-lead sm:hidden">Zamknij</button>
        </div>

        <div id="szukaj-wyniki" role="listbox" aria-label="Wyniki" class="flex-1 overflow-auto overscroll-contain px-2 pt-1.5 pb-3">
            <template x-for="group in groups" x-bind:key="group.label">
                <div role="group" x-bind:aria-label="group.label">
                    <p aria-hidden="true" class="px-3 pt-3 pb-1 text-[10.5px] tracking-[0.22em] text-label uppercase" x-text="group.label"></p>
                    <template x-for="result in group.results" x-bind:key="result.index">
                        <a role="option" tabindex="-1" x-bind:id="optionId(result.index)" x-bind:href="result.url" x-bind:aria-selected="(active === result.index).toString()"
                           x-on:mousemove="active = result.index" x-on:click="chosen(result)"
                           x-bind:class="active === result.index ? 'bg-sand-dark' : ''"
                           class="flex min-h-[50px] items-center gap-3 rounded-[14px] px-3 py-[7px] text-ink hover:text-ink">
                            <template x-if="result.image">
                                <img x-bind:src="result.image" alt="" width="32" height="40" class="h-10 w-8 flex-none rounded-[6px] border border-divider bg-linen object-cover">
                            </template>
                            <template x-if="! result.image">
                                <span aria-hidden="true" class="grid h-10 w-8 flex-none place-items-center rounded-[6px] border border-divider bg-linen text-[13px] text-brown" x-text="result.icon || '·'"></span>
                            </template>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[14px]"><template x-for="part in parts(result.title)"><span x-bind:class="part.hit ? 'font-semibold' : ''" x-text="part.text"></span></template></span>
                                <span x-show="result.detail" class="block truncate text-[12px] text-label" x-text="result.detail"></span>
                            </span>
                            <span x-show="result.meta" class="flex-none text-[12.5px] text-lead tabular-nums" x-text="result.meta"></span>
                        </a>
                    </template>
                </div>
            </template>

            <p x-show="typed() && loading && groups.length === 0" class="px-[18px] py-[30px] text-center text-[13.5px] text-label">Szukam…</p>
            <p x-show="typed() && ! loading && ! failed && groups.length === 0" class="px-[18px] py-[30px] text-center text-[13.5px] text-label">
                Nic nie pasuje do „<span x-text="term.trim()"></span>”.<br>
                {{ $owner ? 'Szukam w produktach, zamówieniach, klientach, warsztatach i ustawieniach.' : 'Szukam w zamówieniach: po numerze, imieniu i nazwisku albo e-mailu.' }}
            </p>
            <p x-show="failed" class="px-[18px] py-[30px] text-center text-[13.5px] text-error">Wyszukiwarka nie odpowiada. Spróbuj jeszcze raz za chwilę.</p>
            <p x-show="! typed() && groups.length === 0" class="px-[18px] py-[30px] text-center text-[13.5px] text-label">Wpisz numer zamówienia, nazwę produktu, imię klienta albo nazwę ustawienia.</p>
        </div>

        <div aria-hidden="true" class="flex flex-wrap gap-x-4 gap-y-1.5 border-t border-divider px-[18px] py-2.5 text-[12px] text-hint max-sm:hidden">
            <span>↑ ↓ wybierasz</span><span>Enter otwiera</span><span>Esc zamyka</span>@if ($owner)<span>Spróbuj: numer zamówienia, „paczkomat”, „próg”</span>@endif
        </div>
        <p role="status" class="sr-only" x-text="status"></p>
    </div>
</div>
