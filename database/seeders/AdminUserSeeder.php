<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin principal
        User::updateOrCreate(
            ['email' => 'admin@esupm.com'],
            [
                'name' => 'Administrateur Principal',
                'email' => 'admin@esupm.com',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'status' => 'active',
                'phone' => '+225 07 00 00 00 01',
                'language' => 'fr',
                'email_verified_at' => now(),
                'loyalty_points' => 0,
                'loyalty_level' => 'bronze',
                'total_points_earned' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Admin secondaire
        User::updateOrCreate(
            ['email' => 'superadmin@esupm.com'],
            [
                'name' => 'Super Administrateur',
                'email' => 'superadmin@esupm.com',
                'password' => Hash::make('SuperAdmin123!'),
                'role' => 'admin',
                'status' => 'active',
                'phone' => '+225 07 00 00 00 02',
                'language' => 'fr',
                'email_verified_at' => now(),
                'loyalty_points' => 0,
                'loyalty_level' => 'bronze',
                'total_points_earned' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Admin de test (pour développement)
        if (app()->environment('local')) {
            User::updateOrCreate(
                ['email' => 'test@admin.com'],
                [
                    'name' => 'Test Admin',
                    'email' => 'test@admin.com',
                    'password' => Hash::make('password'),
                    'role' => 'admin',
                    'status' => 'active',
                    'phone' => '+225 07 00 00 00 03',
                    'language' => 'fr',
                    'email_verified_at' => now(),
                    'loyalty_points' => 1000,
                    'loyalty_level' => 'silver',
                    'total_points_earned' => 1000,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('✅ Admins créés avec succès !');
        $this->command->newLine();
        $this->command->table(
            ['Email', 'Mot de passe', 'Rôle'],
            [
                ['admin@esupm.com', 'admin123', 'admin'],
                ['superadmin@esupm.com', 'SuperAdmin123!', 'admin'],
                app()->environment('local') ? ['test@admin.com', 'password', 'admin'] : null,
            ]
        );
    }
}