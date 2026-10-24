<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class HunterCreateUserCommand extends Command
{
    protected $signature = 'hunter:create-user {email} {name} {--password= : Prompted when omitted}';

    protected $description = 'Create a management user (there is no public registration, D-53)';

    public function handle(): int
    {
        $password = $this->option('password') ?: $this->secret('Password');

        $validator = Validator::make(
            ['email' => $this->argument('email'), 'name' => $this->argument('name'), 'password' => $password],
            ['email' => 'required|email|max:190|unique:users,email', 'name' => 'required|string|max:120', 'password' => 'required|string|min:8'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'password' => Hash::make($password),
        ]);

        $this->info('User created.');

        return self::SUCCESS;
    }
}
