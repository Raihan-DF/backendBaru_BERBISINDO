<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        Role::create([
            'name' => 'Teacher',
            'slug' => 'teacher',
        ]);

        Role::create([
            'name' => 'Student',
            'slug' => 'student',
        ]);
    }
}
