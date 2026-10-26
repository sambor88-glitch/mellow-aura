@use('App\Modules\Admin\Enums\Role')
<x-admin::layout title="Konta" lead="Kto może wejść do panelu. Osoba, która pomaga przy zamówieniach, widzi tylko zamówienia — bez ustawień, cenników i kont.">
    <div class="flex flex-wrap gap-[26px]">
        <section class="grid min-w-0 flex-[1_1_340px] content-start gap-2.5" aria-label="Konta w panelu">
            @foreach ($accounts as $account)
                <article class="flex flex-wrap items-center gap-x-5 gap-y-3 rounded-[4px] border border-line bg-cream px-[18px] py-4">
                    <div class="min-w-0 flex-[1_1_220px]">
                        <h2 class="text-[14.5px] text-ink">{{ $account->name }} @if ($account->is(auth()->user())) <span class="text-[12.5px] text-label">· to Ty</span> @endif</h2>
                        <div class="mt-0.5 text-[12.5px] [overflow-wrap:anywhere] text-label">{{ $account->email }}</div>
                    </div>
                    <span @class(['flex-none rounded-full px-3 py-[5px] text-[11.5px]', 'bg-sand-dark text-lead' => $account->isOwner(), 'bg-navy-soft text-navy' => ! $account->isOwner()])>{{ $account->role->label() }}</span>
                    @unless ($account->is(auth()->user()))
                        <div x-data="{ asking: false }" class="flex flex-none flex-wrap items-center gap-2.5">
                            <form method="post" action="{{ route('admin.accounts.link', $account) }}" x-show="! asking">
                                @csrf
                                <button class="rounded-full border border-line-strong px-4 py-2 text-[12.5px] text-ink hover:border-ink hover:bg-sand-dark">Wyślij link do hasła</button>
                            </form>
                            <button type="button" x-show="! asking" x-on:click="asking = true" aria-label="Usuń dostęp: {{ $account->name }}"
                                    class="rounded-full px-3 py-2 text-[12.5px] text-hint hover:text-error">Usuń dostęp</button>
                            <form method="post" action="{{ route('admin.accounts.destroy', $account) }}" x-show="asking" x-cloak class="flex items-center gap-2.5">
                                @csrf
                                @method('delete')
                                <span class="text-[12.5px] text-error">Na pewno?</span>
                                <button class="rounded-full bg-error px-4 py-2 text-[12.5px] text-linen">Tak, usuń</button>
                                <button type="button" x-on:click="asking = false" class="rounded-full px-3 py-2 text-[12.5px] text-label hover:text-ink">Anuluj</button>
                            </form>
                        </div>
                    @endunless
                </article>
            @endforeach
        </section>

        <section class="min-w-0 flex-[0_1_340px] rounded-[4px] border border-line bg-cream px-[26px] py-7" aria-labelledby="new-account">
            <h2 id="new-account" class="mb-1.5 font-serif text-[23px]">Nowe konto</h2>
            <p class="mb-4 text-[13.5px] leading-[1.55] text-label">Hasła nie wpisujesz — ta osoba dostanie maila z linkiem i sama je ustawi.</p>
            <form method="post" action="{{ route('admin.accounts.store') }}" novalidate class="grid gap-[13px]">
                @csrf
                <x-shared::field name="name" label="Imię" autocomplete="off" />
                <x-shared::field name="email" label="E-mail" type="email" autocomplete="off" />
                <fieldset>
                    <legend class="mb-1.5 text-[13.5px] text-graphite">Co może robić w panelu</legend>
                    <div class="grid gap-2">
                        @foreach ([Role::Helper, Role::Owner] as $role)
                            <label class="flex min-h-11 cursor-pointer items-center gap-2.5 text-[13.5px] text-graphite">
                                <input type="radio" name="role" value="{{ $role->value }}" @checked(old('role', Role::Helper->value) === $role->value) class="size-4 accent-ink">
                                {{ $role->label() }}
                            </label>
                        @endforeach
                    </div>
                    @error('role')
                        <p class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                    @enderror
                </fieldset>
                <button class="w-full rounded-full bg-ink p-4 text-[14px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Dodaj konto i wyślij link</button>
            </form>
        </section>
    </div>
</x-admin::layout>
