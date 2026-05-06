<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin account
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@lms.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Faculty account
        User::create([
            'name' => 'Faculty User',
            'email' => 'faculty@lms.test',
            'password' => Hash::make('password'),
            'role' => 'faculty',
            'email_verified_at' => now(),
        ]);

        // Student user + linked student profile
        $studentUser = User::create([
            'name' => 'Juan Dela Cruz',
            'email' => 'student@lms.test',
            'password' => Hash::make('password'),
            'role' => 'student',
            'email_verified_at' => now(),
        ]);

        Student::create([
            'user_id' => $studentUser->id,
            'student_no' => '2024-00001',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'student@lms.test',
            'course' => 'BSIT',
            'year_level' => 2,
        ]);
    }
}
