<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder AdminSeeder - Creation du compte administrateur.
 * 
 * Cree ou met a jour le compte admin avec les credentials
 * definis dans config/admin.php (via .env).
 * 
 * Usage:
 *   php artisan db:seed --class=AdminSeeder
 *
 * @package Database\Seeders
 */
class AdminSeeder extends Seeder
{
    /**
     * Execute le seeder.
     * 
     * Utilise updateOrCreate pour etre idempotent:
     * - Cree le compte s'il n'existe pas
     * - Met a jour le mot de passe s'il existe
     *
     * @return void
     */
    public function run(): void
    {
        $email = config('admin.email');
        $password = config('admin.password');

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin',
                'password' => Hash::make($password),
            ]
        );

        $this->command->info("Admin user created/updated: {$email}");
    }
}
