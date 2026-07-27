<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            EmailSendersSeeder::class,
            UserSeeder::class,
            PoliciesSeeder::class,
            PlansAndSubscriptionsSeeder::class,
        ]);

        User::factory(50)->create();

        if (app()->isLocal()) {
            $this->call(DeviceManagementDemoSeeder::class);
        }
    }
}
