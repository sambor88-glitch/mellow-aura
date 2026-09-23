<?php

namespace App\Modules\Admin\Console;

use App\Models\User;
use App\Modules\Admin\Enums\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Creates a panel account or sets a new password for it, e.g. the first owner's account on a new server.
 * Later accounts come from the panel's „Konta”, and a forgotten password from „Nie pamiętasz hasła?”.
 * Passwords never go to the repository.
 */
class SetPanelUser extends Command
{
    /** @var string */
    protected $signature = 'admin:user {email : E-mail used to sign in to the panel} {--name= : Name shown in the panel} {--role= : owner (the whole panel) or helper (only the orders)}';

    /** @var string */
    protected $description = 'Create a panel account or set a new password for it';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $password = (string) $this->secret('Password (at least 12 characters)');

        $validator = Validator::make(['email' => $email, 'password' => $password, 'role' => $this->option('role')], [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:12'],
            'role' => ['nullable', Rule::enum(Role::class)],
        ], [
            'role.enum' => 'The role is owner or helper.',
            'email.required' => 'Give the e-mail address.',
            'email.email' => 'This is not an e-mail address.',
            'password.required' => 'Type a password.',
            'password.min' => 'The password needs at least 12 characters.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $this->option('name') ?: ($user->name ?? strstr($email, '@', true));
        $user->password = $password;

        if ($this->option('role')) {
            $user->role = Role::from($this->option('role'));
        }

        $user->save();

        $this->info($user->wasRecentlyCreated ? "Created the panel account for {$email}." : "Set a new password for {$email}.");

        return self::SUCCESS;
    }
}
