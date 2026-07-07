<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user (from original backup)
        $admin = User::firstOrCreate(
            ['email' => 'admin@wawikadio.com'],
            [
                'name' => 'Admin Wawi Kadio',
                'email' => 'admin@wawikadio.com',
                'password' => Hash::make('password'),
                'phone' => '+62812-3456-7890',
                'address' => 'Desa Tonsewer, Kabupaten Minahasa, Sulawesi Utara',
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole('admin');

        // Create manager
        $manager = User::firstOrCreate(
            ['email' => 'manager@wawikadio.com'],
            [
                'name' => 'Manager Wawi Kadio',
                'email' => 'manager@wawikadio.com',
                'password' => Hash::make('password'),
                'phone' => '+62813-9876-5432',
                'address' => 'Desa Tonsewer, Minahasa, Sulawesi Utara',
                'email_verified_at' => now(),
            ]
        );
        $manager->assignRole('manager');

        // Create staff
        $staff = User::firstOrCreate(
            ['email' => 'staff@wawikadio.com'],
            [
                'name' => 'Staff Wawi Kadio',
                'email' => 'staff@wawikadio.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $staff->assignRole('staff');

        // Create demo customer
        $customer = User::firstOrCreate(
            ['email' => 'customer@example.com'],
            [
                'name' => 'Tamu Demo',
                'email' => 'customer@example.com',
                'password' => Hash::make('password'),
                'phone' => '+62811-1234-5678',
                'address' => 'Jakarta Selatan',
                'email_verified_at' => now(),
            ]
        );
        $customer->assignRole('customer');
    }
}
