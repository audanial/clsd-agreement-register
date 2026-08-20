<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create {--name=} {--email=} {--role=viewer}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new user account';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email');
        $role = $this->option('role');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'role' => $role,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', Rule::in(['admin', 'legal', 'viewer'])],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $password = $this->secret('Password');

        if (empty($password)) {
            $this->error('Password is required.');

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'password' => $password,
            'is_active' => true,
        ]);

        $this->info("User [{$email}] created as {$role}.");

        return self::SUCCESS;
    }
}
