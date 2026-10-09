<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        // A first super admin so the system can be used straight after seeding.
        // Change the password after the first login.
        if (! User::role('super admin')->exists()) {
            $admin = User::create([
                'name' => 'Super Admin',
                'email' => 'admin@example.com',
                'phone' => '0500000000',
                'password' => 'password',
            ]);
            $admin->assignRole('super admin');
        }
    }
}
