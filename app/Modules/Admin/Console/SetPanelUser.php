<?php

namespace App\Modules\Admin\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Creates a panel account or sets a new password for it. The site has no sign-up and no reset
 * by e-mail, so this is also how a forgotten password gets replaced. Passwords never go to the repository.
 */
class SetPanelUser extends Command
{
    /** @var string */
    protected $signature = 'admin:user {email : E-mail used to sign in to the panel} {--name= : Name shown in the panel}';

    /** @var string */
    protected $description = 'Create a panel account or set a new password for it';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $password = (string) $this->secret('Password (at least 12 characters)');

        $validator = Validator::make(['email' => $email, 'password' => $password], [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:12'],
        ], [
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
        $user->save();

        $this->info($user->wasRecentlyCreated ? "Created the panel account for {$email}." : "Set a new password for {$email}.");

        return self::SUCCESS;
    }
}
