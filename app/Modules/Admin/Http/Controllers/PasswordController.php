<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * „Nie pamiętasz hasła?”: a link by e-mail and a form for the new password. The answer is the same for every address,
 * so nobody learns which e-mails have a panel account.
 */
class PasswordController extends Controller
{
    private const ROBOTS = 'noindex, nofollow';

    public function request(): Response
    {
        return response()->view('admin::password.request')->header('X-Robots-Tag', self::ROBOTS);
    }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']], [
            'email.required' => 'Wpisz e-mail, którym logujesz się do panelu',
            'email.email' => 'Adres e-mail bez małpy — sprawdź, czy nie uciekła',
        ]);

        Password::sendResetLink(['email' => Str::lower($data['email'])]);

        return back()->with('password_status', 'Jeśli to adres konta w panelu, za chwilę dostaniesz maila z linkiem do nowego hasła.');
    }

    public function edit(Request $request, string $token): Response
    {
        return response()->view('admin::password.reset', ['token' => $token, 'email' => (string) $request->query('email')])
            ->header('X-Robots-Tag', self::ROBOTS);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ], [
            'email.required' => 'Wpisz e-mail, którym logujesz się do panelu',
            'email.email' => 'Adres e-mail bez małpy — sprawdź, czy nie uciekła',
            'password.required' => 'Wpisz nowe hasło',
            'password.min' => 'Hasło potrzebuje co najmniej :min znaków',
            'password.confirmed' => 'Hasła się różnią — wpisz to samo dwa razy',
        ]);

        $status = Password::reset(
            ['email' => Str::lower($data['email']), 'password' => $data['password'], 'token' => $data['token']],
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Ten link już nie działa — poproś o nowy na stronie logowania']);
        }

        return to_route('admin.login')->with('login_status', 'Nowe hasło zapisane — zaloguj się nim.');
    }
}
