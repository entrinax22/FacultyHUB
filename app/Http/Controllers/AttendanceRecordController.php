<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class AttendanceRecordController extends Controller
{
    /**
     * Update attendance records in bulk.
     */
    public function bulkUpdate(
        Request $request,
        string $sessionId
    ): JsonResponse {
        $validated = $request->validate([
            'records' => 'required|array',
            'records.*.student_id' => 'required|string',
            'records.*.status' => 'required|in:present,absent,late,excused',
            'records.*.remarks' => 'nullable|string|max:255',
        ]);

        $session = $this->resolveSession($sessionId);

        if ($session->is_closed) {
            return response()->json([
                'success' => false,
                'message' => 'This session is closed.',
            ], 422);
        }

        foreach ($validated['records'] as $row) {
            $studentId = $this->decryptId($row['student_id']);

            AttendanceRecord::updateOrCreate(
                [
                    'session_id' => $session->id,
                    'student_id' => $studentId,
                ],
                [
                    'status' => $row['status'],
                    'remarks' => $row['remarks'] ?? null,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Attendance saved.',
        ]);
    }

    /**
     * Mark all students in an attendance session with the same status.
     */
    public function markAll(
        Request $request,
        string $sessionId
    ): JsonResponse {
        $validated = $request->validate([
            'status' => 'required|in:present,absent,late,excused',
        ]);

        $session = $this->resolveSession($sessionId);

        if ($session->is_closed) {
            return response()->json([
                'success' => false,
                'message' => 'This session is closed.',
            ], 422);
        }

        AttendanceRecord::query()
            ->where('session_id', $session->id)
            ->update([
                'status' => $validated['status'],
            ]);

        return response()->json([
            'success' => true,
            'message' => 'All students marked as '
                . ucfirst($validated['status'])
                . '.',
        ]);
    }

    /**
     * Resolve an AttendanceSession from an encrypted ID.
     */
    private function resolveSession(string $encryptedId): AttendanceSession
    {
        $id = $this->decryptId($encryptedId);

        return AttendanceSession::query()->findOrFail($id);
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
}