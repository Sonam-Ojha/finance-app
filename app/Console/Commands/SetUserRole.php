<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Makes someone an admin from the server's command line — how the very first
 * admin is made, before anyone can use the Users page.
 *
 *   php artisan user:admin you@example.com
 *   php artisan user:admin you@example.com --remove
 */
class SetUserRole extends Command
{
    protected $signature = 'user:admin {email : The account\'s email} {--remove : Take admin away instead}';

    protected $description = 'Make a user an admin (or, with --remove, an ordinary user)';

    public function handle(): int
    {
        $user = User::where('email', strtolower(trim($this->argument('email'))))->first();
        if (! $user) {
            $this->error('No account uses that email.');
            return self::FAILURE;
        }

        $role = $this->option('remove') ? User::ROLE_USER : User::ROLE_ADMIN;
        $user->forceFill(['role' => $role])->save();

        $this->info("{$user->email} is now " . ($role === User::ROLE_ADMIN ? 'an admin.' : 'an ordinary user.'));
        return self::SUCCESS;
    }
}
