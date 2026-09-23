<x-admin::layout title="Mój panel">
    <div class="max-w-[420px] rounded-[4px] border border-line bg-cream px-8 py-[34px]">
        <h2 class="mb-1.5 font-serif text-[25px]">Ustaw hasło</h2>
        <p class="mb-5 text-[13.5px] leading-[1.6] text-label">Co najmniej 12 znaków. Najłatwiej zapamiętać kilka słów, które razem nic nie znaczą.</p>

        <form method="post" action="{{ route('admin.password.update') }}" novalidate class="grid gap-3">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <x-shared::field name="email" label="E-mail" type="email" autocomplete="username" :value="old('email', $email)" />
            <x-shared::field name="password" label="Nowe hasło" type="password" autocomplete="new-password" autofocus />
            <x-shared::field name="password_confirmation" label="To samo hasło jeszcze raz" type="password" autocomplete="new-password" />
            <button class="rounded-full bg-ink p-[15px] text-[14.5px] text-linen transition duration-300 hover:bg-navy active:scale-[.97]">Zapisz hasło</button>
        </form>
    </div>
</x-admin::layout>
