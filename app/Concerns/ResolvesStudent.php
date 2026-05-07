<?php

namespace App\Concerns;

use App\Models\Student;
use Illuminate\Http\Request;

trait ResolvesStudent
{
    protected function resolveStudent(Request $request): Student
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if ($user->student) {
            return $user->student;
        }

        // Auto-link: admin may have pre-created a Student record with the same email
        $student = Student::where('email', $user->email)->first();

        if (! $student) {
            abort(403, 'No student profile is linked to this account.');
        }

        if ($student->user_id && (int) $student->user_id !== (int) $user->id) {
            abort(403, 'This student profile is already linked to another account.');
        }

        $student->forceFill(['user_id' => $user->id])->save();

        return $student->fresh();
    }
}
