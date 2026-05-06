<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceSessionController extends Controller
{
    public function index(Section $section): Response
    {
        $section->load(['subject', 'semester']);

        $sessions = AttendanceSession::where('section_id', $section->id)
            ->withCount([
                'records',
                'records as present_count' => fn ($q) => $q->where('status', 'present'),
                'records as absent_count' => fn ($q) => $q->where('status', 'absent'),
                'records as late_count' => fn ($q) => $q->where('status', 'late'),
            ])
            ->orderByDesc('date')
            ->get();

        $studentCount = Enrollment::where('section_id', $section->id)
            ->where('status', 'active')
            ->count();

        return Inertia::render('attendance/Index', [
            'section' => $section,
            'sessions' => $sessions,
            'studentCount' => $studentCount,
        ]);
    }

    public function store(Request $request, Section $section): RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'topic' => 'nullable|string|max:255',
        ]);

        $session = AttendanceSession::create([
            'section_id' => $section->id,
            'created_by' => $request->user()->id,
            'date' => $validated['date'],
            'topic' => $validated['topic'] ?? null,
        ]);

        // Pre-fill all enrolled students as "present"
        $students = Enrollment::where('section_id', $section->id)
            ->where('status', 'active')
            ->pluck('student_id');

        foreach ($students as $studentId) {
            AttendanceRecord::create([
                'session_id' => $session->id,
                'student_id' => $studentId,
                'status' => 'present',
            ]);
        }

        return redirect()
            ->route('attendance.session', [$section, $session])
            ->with('success', 'Attendance session opened. All students pre-marked Present.');
    }

    public function show(Section $section, AttendanceSession $session): Response
    {
        $section->load(['subject', 'semester']);

        $enrollments = Enrollment::where('section_id', $section->id)
            ->where('status', 'active')
            ->with('student')
            ->get()
            ->sortBy('student.last_name');

        $records = AttendanceRecord::where('session_id', $session->id)
            ->get()
            ->keyBy('student_id');

        $students = $enrollments->map(fn ($e) => [
            'student' => $e->student,
            'record' => $records->get($e->student_id),
        ])->values();

        return Inertia::render('attendance/Session', [
            'section' => $section,
            'session' => $session,
            'students' => $students,
        ]);
    }

    public function close(AttendanceSession $session): RedirectResponse
    {
        $session->update(['is_closed' => true]);

        return redirect()
            ->route('attendance.index', $session->section_id)
            ->with('success', 'Session closed.');
    }

    public function destroy(AttendanceSession $session): RedirectResponse
    {
        $sectionId = $session->section_id;
        $session->delete();

        return redirect()
            ->route('attendance.index', $sectionId)
            ->with('success', 'Session deleted.');
    }

    public function summary(Section $section): Response
    {
        $section->load(['subject', 'semester']);

        $sessions = AttendanceSession::where('section_id', $section->id)->get();
        $totalSessions = $sessions->count();

        $enrollments = Enrollment::where('section_id', $section->id)
            ->where('status', 'active')
            ->with('student')
            ->get()
            ->sortBy('student.last_name');

        $allRecords = AttendanceRecord::whereIn('session_id', $sessions->pluck('id'))
            ->get()
            ->groupBy('student_id');

        $rows = $enrollments->map(function ($enrollment) use ($allRecords, $totalSessions) {
            $student = $enrollment->student;
            $records = $allRecords->get($student->id, collect());

            $present = $records->where('status', 'present')->count();
            $late = $records->where('status', 'late')->count();
            $absent = $records->where('status', 'absent')->count();
            $excused = $records->where('status', 'excused')->count();

            $attended = $present + $late;
            $percentage = $totalSessions > 0
                ? round(($attended / $totalSessions) * 100, 2)
                : null;

            return [
                'student' => $student,
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'excused' => $excused,
                'attended' => $attended,
                'total' => $totalSessions,
                'percentage' => $percentage,
            ];
        })->values();

        return Inertia::render('attendance/Summary', [
            'section' => $section,
            'totalSessions' => $totalSessions,
            'rows' => $rows,
        ]);
    }
}
