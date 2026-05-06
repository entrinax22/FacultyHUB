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
        User::updateOrCreate(
            ['email' => 'admin@lms.test'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        // Faculty account
        User::updateOrCreate(
            ['email' => 'faculty@lms.test'],
            [
                'name' => 'Faculty User',
                'password' => Hash::make('password'),
                'role' => 'faculty',
                'email_verified_at' => now(),
            ]
        );

        // Student user + linked student profile
        $studentUser = User::updateOrCreate(
            ['email' => 'student@lms.test'],
            [
                'name' => 'Juan Dela Cruz',
                'password' => Hash::make('password'),
                'role' => 'student',
                'email_verified_at' => now(),
            ]
        );

        Student::updateOrCreate(
            ['user_id' => $studentUser->id],
            [
                'student_no' => '2024-00001',
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'email' => 'student@lms.test',
                'course' => 'BSIT',
                'year_level' => 2,
            ]
        );
    }
}
