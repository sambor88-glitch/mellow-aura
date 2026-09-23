<x-admin::layout title="Mój panel">
    <div class="max-w-[420px] rounded-[4px] border border-line bg-cream px-8 py-[34px]">
        <h2 class="mb-1.5 font-serif text-[25px]">Nowe hasło</h2>
        <p class="mb-5 text-[13.5px] leading-[1.6] text-label">Wpisz e-mail, którym logujesz się do panelu. Wyślę na niego link do ustawienia nowego hasła.</p>

        @if (session('password_status'))
            <p role="status" class="mb-5 rounded-[4px] border border-success-line bg-success-soft px-3.5 py-3 text-[13.5px] leading-[1.55] text-success-text">{{ session('password_status') }}</p>
        @endif

        <form method="post" action="{{ route('admin.password.email') }}" novalidate class="grid gap-3">
            @csrf
            <x-shared::field name="email" label="E-mail" type="email" autocomplete="username" autofocus />
            <button class="rounded-full bg-ink p-[15px] text-[14.5px] text-linen transition duration-300 hover:bg-navy active:scale-[.97]">Wyślij link do nowego hasła</button>
        </form>
        <a href="{{ route('admin.login') }}" class="mt-5 inline-block text-[13.5px]">← Wróć do logowania</a>
    </div>
</x-admin::layout>
