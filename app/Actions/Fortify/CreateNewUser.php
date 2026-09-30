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
                'name' => $input['first_name'] . " " . $input['last_name'],
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name'],
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


            Student::create([
                'user_id'    => $user->id,
                'student_no' => $input['student_no'],
                'first_name' => $input['first_name'],
                'last_name'  => $input['last_name'],
                'email'      => $user->email,
                'course'     => $input['course'],
                'year_level' => $input['year_level'] ?? 1,
            ]);

            return $user;
        });
    }
}
