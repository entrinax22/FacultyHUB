<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\StudentActivityNotification;
use Illuminate\Support\Facades\Crypt;

class StudentNotificationService
{
    public function notifySectionStudents(int $sectionId, array $data): void
    {
        $studentIds = Enrollment::query()
            ->where('section_id', $sectionId)
            ->where('status', 'active')
            ->pluck('student_id');

        $this->notifyStudents($studentIds, $data);
    }

    public function notifyStudents(iterable $studentIds, array $data): void
    {
        $userIds = Student::query()
            ->whereIn('id', collect($studentIds)->unique())
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique();

        $this->notifyUsers($userIds, $data, ['student']);
    }

    public function notifyAssignmentStaff(
        Assignment $assignment,
        Student $student,
        Submission $submission
    ): void {
        $assignment->loadMissing('section');

        $userIds = User::query()
            ->where('role', 'admin')
            ->pluck('id');

        if ($assignment->section?->faculty_id) {
            $userIds->push($assignment->section->faculty_id);
        }

        $this->notifyUsers(
            $userIds,
            [
                'type' => 'assignment_submission',
                'title' => 'Assignment submitted',
                'message' => "{$student->first_name} {$student->last_name} submitted {$assignment->title}.",
                'url' => '/submissions/'.Crypt::encryptString((string) $submission->id).'/grade',
            ],
            ['admin', 'faculty']
        );
    }

    private function notifyUsers(
        iterable $userIds,
        array $data,
        array $roles
    ): void {
        User::query()
            ->whereKey(collect($userIds)->unique())
            ->whereIn('role', $roles)
            ->get()
            ->each(fn (User $user) => $user->notify(
                new StudentActivityNotification($data)
            ));
    }
}
