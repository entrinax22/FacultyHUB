<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    private function splitName(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);

        if ($name === '') {
            return ['first' => 'Student', 'last' => ''];
        }

        $parts = explode(' ', $name, 2);

        return [
            'first' => $parts[0] ?? 'Student',
            'last' => $parts[1] ?? '',
        ];
    }

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'student_no' => ['required', 'string', 'regex:/^\d{4}-\d{5}$/', 'unique:students,student_no'],
            'password' => $this->passwordRules(),
        ], [
            'student_no.regex' => 'Student ID must be in the format YYYY-NNNNN (e.g. 2020-00015).',
            'student_no.unique' => 'This student ID is already registered.',
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'role' => 'student',
            ]);

            // If an admin already created a Student record for this email, link it.
            $student = Student::where('email', $user->email)
                ->orWhere('student_no', $input['student_no'])
                ->first();

            if ($student) {
                if ($student->user_id && (int) $student->user_id !== (int) $user->id) {
                    return $user;
                }

                $student->forceFill(['user_id' => $user->id])->save();

                return $user;
            }

            $nameParts = $this->splitName($user->name);

            Student::create([
                'user_id'    => $user->id,
                'student_no' => $input['student_no'],
                'first_name' => $nameParts['first'],
                'last_name'  => $nameParts['last'],
                'email'      => $user->email,
                'course'     => 'N/A',
                'year_level' => 1,
            ]);

            return $user;
        });
    }
}
