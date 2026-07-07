<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Facility;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        $this->call([
            RoleSeeder::class,
            FacilitySeeder::class,
            MenuItemSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
