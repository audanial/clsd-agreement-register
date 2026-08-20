<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ResetUserPasswordCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:password {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset a user\'s password';

    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->error("No user found for [{$email}].");

            return self::FAILURE;
        }

        $password = $this->secret('New password');
        $confirmation = $this->secret('Confirm new password');

        if ($password !== $confirmation) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        if (empty($password)) {
            $this->error('Password is required.');

            return self::FAILURE;
        }

        $user->password = $password;
        $user->setRememberToken(Str::random(60));
        $user->save();

        $this->info("Password reset for [{$email}]. Existing sessions have been invalidated.");

        return self::SUCCESS;
    }
}
