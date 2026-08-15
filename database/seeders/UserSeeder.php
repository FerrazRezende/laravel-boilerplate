<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create God Admin user (system owner)
        User::create([
            'name' => 'Admin',
            'email' => 'admin@boilerplate.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_admin' => true,
            'active' => true,
            'password_changed_at' => now(),
        ]);

        // No default roles - admin creates them via UI

        // Create 10 test users
        $users = [
            ['name' => 'Alice Santos', 'email' => 'alice@boilerplate.com'],
            ['name' => 'Bob Ferreira', 'email' => 'bob@boilerplate.com'],
            ['name' => 'Carlos Lima', 'email' => 'carlos@boilerplate.com'],
            ['name' => 'Diana Costa', 'email' => 'diana@boilerplate.com'],
            ['name' => 'Eduardo Silva', 'email' => 'eduardo@boilerplate.com'],
            ['name' => 'Fernanda Rocha', 'email' => 'fernanda@boilerplate.com'],
            ['name' => 'Gabriel Mendes', 'email' => 'gabriel@boilerplate.com'],
            ['name' => 'Helena Alves', 'email' => 'helena@boilerplate.com'],
            ['name' => 'Igor Nascimento', 'email' => 'igor@boilerplate.com'],
            ['name' => 'Julia Oliveira', 'email' => 'julia@boilerplate.com'],
        ];

        foreach ($users as $userData) {
            User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_admin' => false,
                'active' => true,
                'password_changed_at' => now(),
            ]);
        }
    }
}
