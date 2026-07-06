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
        throw_if(empty(config('panel.admin.email')), \Exception::class, 'ADMIN_EMAIL is not set in .env');
        throw_if(empty(config('panel.admin.password')), \Exception::class, 'ADMIN_PASSWORD is not set in .env');

        // Create admin user
        $admin = User::create([
            'name' => config('panel.admin.name'),
            'email' => config('panel.admin.email'),
            'password' => Hash::make(config('panel.admin.password')),
            'email_verified_at' => now(),
            'type' => 'staff',
        ]);
        $admin->assignRole(config('panel.super_admin_role'));

        // Create regular user
        if (app()->isLocal()) {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'user@user.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
            $user->assignRole(config('panel.app_user_role'));
        }
    }
}