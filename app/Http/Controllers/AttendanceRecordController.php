<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttendanceRecordController extends Controller
{
    public function bulkUpdate(Request $request, AttendanceSession $session): RedirectResponse
    {
        if ($session->is_closed) {
            return back()->withErrors(['session' => 'This session is closed.']);
        }

        $validated = $request->validate([
            'records' => 'required|array',
            'records.*.student_id' => 'required|exists:students,id',
            'records.*.status' => 'required|in:present,absent,late,excused',
            'records.*.remarks' => 'nullable|string|max:255',
        ]);

        foreach ($validated['records'] as $row) {
            AttendanceRecord::updateOrCreate(
                ['session_id' => $session->id, 'student_id' => $row['student_id']],
                ['status' => $row['status'], 'remarks' => $row['remarks'] ?? null]
            );
        }

        return back()->with('success', 'Attendance saved.');
    }

    public function markAll(Request $request, AttendanceSession $session): RedirectResponse
    {
        if ($session->is_closed) {
            return back()->withErrors(['session' => 'This session is closed.']);
        }

        $validated = $request->validate([
            'status' => 'required|in:present,absent,late,excused',
        ]);

        AttendanceRecord::where('session_id', $session->id)
            ->update(['status' => $validated['status']]);

        return back()->with('success', 'All students marked as '.ucfirst($validated['status']).'.');
    }
}
