<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // Create a teacher
        // $teacher = User::create([
        //     'name' => 'Teacher',
        //     'email' => 'teacher@example.com',
        //     'password' => Hash::make('password'),
        // ]);

        // $teacherRole = Role::where('slug', 'teacher')->first();
        // $teacher->roles()->attach($teacherRole);

        // Create a student
        $student = User::create([
                'name' => 'Alice Smith',
                'email' => 'alice.student@bisindo.com',
                'password' => Hash::make('password'),   
        ]);

        $studentRole = Role::where('slug', 'student')->first();
        $student->roles()->attach($studentRole);
    }
}
