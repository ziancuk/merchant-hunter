<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        // Never creates demo accounts in production (D-69).
        if (app()->environment('production')) {
            return;
        }

        $password = env('DEMO_USER_PASSWORD', 'demo-password-123');
        foreach ([['Ayu Management', 'ayu@hunter.test'], ['Budi Management', 'budi@hunter.test'], ['Citra Management', 'citra@hunter.test']] as [$name, $email]) {
            User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => Hash::make($password)]);
        }
    }
}
