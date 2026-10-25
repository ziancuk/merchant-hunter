<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        // Never creates demo accounts in production (D-69).
        if (app()->environment('production')) {
            return;
        }

        $password = (string) config('hunter.demo_user_password', '');
        if ($password === '') {
            // The well-known default is only acceptable on throwaway environments (D-123).
            if (! app()->environment(['local', 'e2e', 'testing'])) {
                throw new RuntimeException('DEMO_USER_PASSWORD wajib diisi di environment "'.app()->environment().'". Isi di .env atau jalankan hanya di local/e2e/testing.');
            }
            $password = 'demo-password-123';
        }
        foreach ([['Ayu Management', 'ayu@hunter.test'], ['Budi Management', 'budi@hunter.test'], ['Citra Management', 'citra@hunter.test']] as [$name, $email]) {
            User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => Hash::make($password)]);
        }
    }
}
