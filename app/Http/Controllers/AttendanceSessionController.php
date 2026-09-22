<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;
use Barryvdh\DomPDF\Facade\Pdf;

class AttendanceSessionController extends Controller
{
    /**
     * Display the attendance page.
     *
     * Inertia page only.
     */
    public function page(string $sectionId): Response
    {
        $section = $this->resolveSection($sectionId);

        $section->load([
            'subject',
            'semester',
        ]);

        return Inertia::render('attendance/Index', [
            'section' => $this->transformSection($section),
        ]);
    }

    /**
     * Load attendance sessions for a section.
     *
     * JSON data endpoint.
     */
    public function index(string $sectionId): JsonResponse
    {
        $section = $this->resolveSection($sectionId);

        $section->load([
            'subject',
            'semester',
        ]);

        $sessions = AttendanceSession::query()
            ->where('section_id', $section->id)
            ->withCount([
                'records',

                'records as present_count' => fn ($query) =>
                    $query->where('status', 'present'),

                'records as absent_count' => fn ($query) =>
                    $query->where('status', 'absent'),

                'records as late_count' => fn ($query) =>
                    $query->where('status', 'late'),
            ])
            ->orderByDesc('date')
            ->get();

        $studentCount = Enrollment::query()
            ->where('section_id', $section->id)
            ->where('status', 'active')
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Attendance sessions loaded successfully.',
            'data' => [
                'section' => $this->transformSection($section),

                'sessions' => $sessions
                    ->map(fn ($session) => $this->transformSession($session))
                    ->values(),

                'student_count' => $studentCount,
            ],
        ]);
    }

    public function sessionPage(
        string $sectionId,
        string $sessionId
    ): Response {
        $section = $this->resolveSection($sectionId);
        $session = $this->resolveSession($sessionId);

        if ((int) $session->section_id !== (int) $section->id) {
            abort(404, 'Attendance session not found.');
        }

        $section->load([
            'subject',
            'semester',
        ]);

        return Inertia::render('attendance/Session', [
            'section' => $this->transformSection($section),
            'session' => $this->transformSession($session),
        ]);
    }

    /**
     * Create a new attendance session.
     *
     * JSON mutation endpoint.
     */
    public function store(
        Request $request,
        string $sectionId
    ): JsonResponse {
        $validated = $request->validate([
            'date' => 'required|date',
            'topic' => 'nullable|string|max:255',
        ]);

        $section = $this->resolveSection($sectionId);

        $session = AttendanceSession::query()->create([
            'section_id' => $section->id,
            'created_by' => $request->user()->id,
            'date' => $validated['date'],
            'topic' => $validated['topic'] ?? null,
        ]);

        /*
         * Pre-fill all active enrolled students as Present.
         */
        $students = Enrollment::query()
            ->where('section_id', $section->id)
            ->where('status', 'active')
            ->pluck('student_id');

        foreach ($students as $studentId) {
            AttendanceRecord::query()->create([
                'session_id' => $session->id,
                'student_id' => $studentId,
                'status' => 'present',
            ]);
        }

        $session->loadCount([
            'records',

            'records as present_count' => fn ($query) =>
                $query->where('status', 'present'),

            'records as absent_count' => fn ($query) =>
                $query->where('status', 'absent'),

            'records as late_count' => fn ($query) =>
                $query->where('status', 'late'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attendance session opened. All students pre-marked Present.',
            'data' => [
                'session' => $this->transformSession($session),
            ],
        ], 201);
    }

    /**
     * Load a specific attendance session.
     *
     * JSON data endpoint.
     */
    public function show(
        string $sectionId,
        string $sessionId
    ): JsonResponse {
        $section = $this->resolveSection($sectionId);
        $session = $this->resolveSession($sessionId);

        /*
         * Make sure the session belongs to the requested section.
         */
        if ((int) $session->section_id !== (int) $section->id) {
            abort(404, 'Attendance session not found.');
        }

        $section->load([
            'subject',
            'semester',
        ]);

        $enrollments = Enrollment::query()
            ->where('section_id', $section->id)
            ->where('status', 'active')
            ->with('student')
            ->get()
            ->sortBy('student.last_name');

        $records = AttendanceRecord::query()
            ->where('session_id', $session->id)
            ->get()
            ->keyBy('student_id');

        $students = $enrollments
            ->map(function ($enrollment) use ($records) {
                $student = $enrollment->student;

                $record = $records->get($student->id);

                return [
                    'student' => [
                        'id' => $this->encryptId($student->id),
                        'student_no' => $student->student_no,
                        'first_name' => $student->first_name,
                        'last_name' => $student->last_name,
                        'middle_name' => $student->middle_name,
                    ],

                    'record' => $record
                        ? $this->transformRecord($record)
                        : null,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Attendance session loaded successfully.',
            'data' => [
                'section' => $this->transformSection($section),
                'session' => $this->transformSession($session),
                'students' => $students,
            ],
        ]);
    }

    /**
     * Close an attendance session.
     *
     * JSON mutation endpoint.
     */
    public function close(string $sessionId): JsonResponse
    {
        $session = $this->resolveSession($sessionId);

        if ($session->is_closed) {
            return response()->json([
                'success' => false,
                'message' => 'This attendance session is already closed.',
            ], 422);
        }

        $session->update([
            'is_closed' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attendance session closed.',
            'data' => [
                'session' => $this->transformSession(
                    $session->fresh()
                ),
            ],
        ]);
    }

    /**
     * Delete an attendance session.
     *
     * JSON mutation endpoint.
     */
    public function destroy(string $sessionId): JsonResponse
    {
        $session = $this->resolveSession($sessionId);

        $session->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attendance session deleted.',
        ]);
    }

    public function summaryPage(string $sectionId): Response
    {
        $section = $this->resolveSection($sectionId);

        $section->load([
            'subject',
            'semester',
        ]);

        return Inertia::render('attendance/Summary', [
            'section' => $this->transformSection($section),
        ]);
    }

    /**
     * Load attendance summary for a section.
     *
     * JSON data endpoint.
     */
    public function summary(string $sectionId): JsonResponse
    {
        $section = $this->resolveSection($sectionId);

        $section->load([
            'subject',
            'semester',
        ]);

        $sessions = AttendanceSession::query()
            ->where('section_id', $section->id)
            ->get();

        $totalSessions = $sessions->count();

        $enrollments = Enrollment::query()
            ->where('section_id', $section->id)
            ->where('status', 'active')
            ->with('student')
            ->get()
            ->sortBy('student.last_name');

        $allRecords = AttendanceRecord::query()
            ->whereIn(
                'session_id',
                $sessions->pluck('id')
            )
            ->get()
            ->groupBy('student_id');

        $rows = $enrollments
            ->map(function ($enrollment) use (
                $allRecords,
                $totalSessions
            ) {
                $student = $enrollment->student;

                $records = $allRecords->get(
                    $student->id,
                    collect()
                );

                $present = $records
                    ->where('status', 'present')
                    ->count();

                $late = $records
                    ->where('status', 'late')
                    ->count();

                $absent = $records
                    ->where('status', 'absent')
                    ->count();

                $excused = $records
                    ->where('status', 'excused')
                    ->count();

                $attended = $present + $late;

                $percentage = $totalSessions > 0
                    ? round(
                        ($attended / $totalSessions) * 100,
                        2
                    )
                    : null;

                return [
                    'student' => [
                        'id' => $this->encryptId($student->id),
                        'first_name' => $student->first_name,
                        'last_name' => $student->last_name,
                        'middle_name' => $student->middle_name,
                        'student_no' => $student->student_no,
                    ],

                    'present' => $present,
                    'late' => $late,
                    'absent' => $absent,
                    'excused' => $excused,
                    'attended' => $attended,
                    'total' => $totalSessions,
                    'percentage' => $percentage,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Attendance summary loaded successfully.',
            'data' => [
                'section' => $this->transformSection($section),
                'total_sessions' => $totalSessions,
                'rows' => $rows,
            ],
        ]);
    }

    /**
     * Resolve a section from an encrypted ID.
     */
    private function resolveSection(string $encryptedId): Section
    {
        $id = $this->decryptId($encryptedId);

        return Section::query()->findOrFail($id);
    }

    /**
     * Resolve an attendance session from an encrypted ID.
     */
    private function resolveSession(string $encryptedId): AttendanceSession
    {
        $id = $this->decryptId($encryptedId);

        return AttendanceSession::query()->findOrFail($id);
    }

    /**
     * Transform a section for JSON/Inertia response.
     */
    private function transformSection(Section $section): array
    {
        return [
            'id' => $this->encryptId($section->id),
            'name' => $section->name,

            'subject' => $section->subject
                ? [
                    'id' => $this->encryptId(
                        $section->subject->id
                    ),
                    'code' => $section->subject->code,
                    'name' => $section->subject->name,
                ]
                : null,

            'semester' => $section->semester
                ? [
                    'id' => $this->encryptId(
                        $section->semester->id
                    ),
                    'name' => $section->semester->name,
                ]
                : null,
        ];
    }

    /**
     * Transform an attendance session for JSON response.
     */
    private function transformSession(
        AttendanceSession $session
    ): array {
        return [
            'id' => $this->encryptId($session->id),

            'section_id' => $this->encryptId(
                $session->section_id
            ),

            'created_by' => $session->created_by
                ? $this->encryptId($session->created_by)
                : null,

            'date' => $session->date,
            'topic' => $session->topic,
            'is_closed' => (bool) $session->is_closed,

            'records_count' => $session->records_count ?? null,
            'present_count' => $session->present_count ?? null,
            'absent_count' => $session->absent_count ?? null,
            'late_count' => $session->late_count ?? null,
        ];
    }

    /**
     * Transform an attendance record for JSON response.
     */
    private function transformRecord(
        AttendanceRecord $record
    ): array {
        return [
            'id' => $this->encryptId($record->id),

            'session_id' => $this->encryptId(
                $record->session_id
            ),

            'student_id' => $this->encryptId(
                $record->student_id
            ),

            'status' => $record->status,
            'remarks' => $record->remarks,
        ];
    }

    /**
     * Decrypt an encrypted database ID.
     */
    private function decryptId(string $encryptedId): int
    {
        try {
            return (int) Crypt::decryptString($encryptedId);
        } catch (\Throwable $e) {
            abort(404, 'Invalid resource identifier.');
        }
    }

    /**
     * Encrypt a database ID.
     */
    private function encryptId(int $id): string
    {
        return Crypt::encryptString((string) $id);
    }

    public function exportSummaryPdf(string $sectionId)
    {
        $section = $this->resolveSection($sectionId);

        $section->load([
            'subject',
            'semester',
        ]);

        $sessions = AttendanceSession::query()
            ->where('section_id', $section->id)
            ->orderBy('date')
            ->get();

        $totalSessions = $sessions->count();

        $enrollments = Enrollment::query()
            ->where('section_id', $section->id)
            ->where('status', 'active')
            ->with('student')
            ->get()
            ->sortBy([
                ['student.last_name', 'asc'],
                ['student.first_name', 'asc'],
            ]);

        $allRecords = AttendanceRecord::query()
            ->whereIn(
                'session_id',
                $sessions->pluck('id')
            )
            ->get()
            ->groupBy('student_id');

        $rows = $enrollments
            ->map(function ($enrollment) use (
                $allRecords,
                $totalSessions
            ) {
                $student = $enrollment->student;

                $records = $allRecords->get(
                    $student->id,
                    collect()
                );

                $present = $records
                    ->where('status', 'present')
                    ->count();

                $late = $records
                    ->where('status', 'late')
                    ->count();

                $absent = $records
                    ->where('status', 'absent')
                    ->count();

                $excused = $records
                    ->where('status', 'excused')
                    ->count();

                $attended = $present + $late;

                $percentage = $totalSessions > 0
                    ? round(
                        ($attended / $totalSessions) * 100,
                        2
                    )
                    : null;

                return [
                    'student_number' => $student->student_number,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'middle_name' => $student->middle_name,
                    'student_no' => $student->student_no,

                    'present' => $present,
                    'late' => $late,
                    'absent' => $absent,
                    'excused' => $excused,
                    'attended' => $attended,
                    'total' => $totalSessions,
                    'percentage' => $percentage,
                ];
            })
            ->values();

        $pdf = Pdf::loadView('pdf.attendance-summary', [
            'section' => $section,
            'sessions' => $sessions,
            'totalSessions' => $totalSessions,
            'rows' => $rows,
        ])
            ->setPaper('a4', 'landscape');

        $filename = sprintf(
            'attendance-summary-%s.pdf',
            str_replace(
                [' ', '/'],
                ['-', '-'],
                $section->name
            )
        );

        return $pdf->stream($filename);
    }
}