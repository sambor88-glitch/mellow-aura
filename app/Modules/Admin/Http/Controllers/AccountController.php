<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Admin\Enums\Role;
use App\Modules\Admin\Mail\PanelPasswordLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * „Konta”, for the owner only: who may use the panel. A new account gets no password from the owner — its person
 * sets one through the link in the e-mail. The owner can't remove herself or the last owner.
 */
class AccountController extends Controller
{
    public function index(): View
    {
        return view('admin::accounts.index', [
            'accounts' => User::query()->orderByRaw("role = 'owner' desc")->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(Role::class)],
        ], [
            'name.required' => 'Wpisz imię — tak zobaczysz to konto na liście',
            'name.max' => 'Imię zmieszczę do :max znaków',
            'email.required' => 'Wpisz e-mail — na niego pójdzie link do hasła',
            'email.email' => 'Adres e-mail bez małpy — sprawdź, czy nie uciekła',
            'email.unique' => 'To konto już jest na liście',
            'role.required' => 'Wybierz, co ta osoba może robić w panelu',
            'role.enum' => 'Wybierz, co ta osoba może robić w panelu',
        ]);

        // Nobody knows this password: the person sets her own through the link.
        $account = User::create([...$data, 'password' => Str::password(40)]);
        $this->sendLink($account);

        return to_route('admin.accounts.index')->with('panel_status', 'Konto dodane — link do hasła poszedł na '.$account->email.'.');
    }

    public function link(User $user): RedirectResponse
    {
        $this->sendLink($user);

        return to_route('admin.accounts.index')->with('panel_status', 'Nowy link do hasła poszedł na '.$user->email.'.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $lastOwner = $user->isOwner() && User::query()->where('role', Role::Owner)->count() === 1;

        if ($user->is($request->user()) || $lastOwner) {
            return to_route('admin.accounts.index')->with('panel_status', 'Tego konta nie usunę — panel musi mieć właścicielkę.');
        }

        DB::transaction(function () use ($user) {
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $user->delete();
        });

        return to_route('admin.accounts.index')->with('panel_status', 'Dostęp usunięty — '.$user->email.' nie wejdzie już do panelu.');
    }

    private function sendLink(User $user): void
    {
        Mail::to($user->email)->send(new PanelPasswordLink($user, Password::broker()->createToken($user), invitation: true));
    }
}
