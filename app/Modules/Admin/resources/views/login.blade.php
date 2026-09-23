<x-admin::layout title="Mój panel">
    <div class="max-w-[420px] rounded-[4px] border border-line bg-cream px-8 py-[34px]">
        <h2 class="mb-1.5 font-serif text-[25px]">Zaloguj się</h2>
        <p class="mb-5 text-[13.5px] leading-[1.6] text-label">Panel jest tylko dla Ciebie. Wpisz e-mail i hasło do panelu.</p>
        @if (session('login_status'))
            <p role="status" class="mb-5 rounded-[4px] border border-success-line bg-success-soft px-3.5 py-3 text-[13.5px] leading-[1.55] text-success-text">{{ session('login_status') }}</p>
        @endif
        <form method="post" action="{{ route('admin.login.store') }}" novalidate class="grid gap-3">
            @csrf
            <x-shared::field name="email" label="E-mail" type="email" autocomplete="username" autofocus />
            <x-shared::field name="password" label="Hasło" type="password" autocomplete="current-password" />
            <label class="flex min-h-11 items-center gap-2.5 text-[13.5px] text-graphite">
                <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="size-4 accent-ink">
                Nie wylogowuj mnie na tym urządzeniu
            </label>
            <button class="rounded-full bg-ink p-[15px] text-[14.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Wejdź do panelu</button>
        </form>
        <a href="{{ route('admin.password.request') }}" class="mt-5 inline-block text-[13.5px]">Nie pamiętasz hasła?</a>
    </div>
</x-admin::layout>
