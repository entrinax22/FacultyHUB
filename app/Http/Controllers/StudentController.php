<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(): Response
    {
        $students = Student::withCount('enrollments')->latest()->paginate(20);

        return Inertia::render('students/Index', [
            'students' => $students,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('students/Form');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_no' => 'required|string|max:30|unique:students,student_no',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:students,email',
            'course' => 'required|string|max:100',
            'year_level' => 'required|integer|min:1|max:6',
        ]);

        Student::create($validated);

        return redirect()->route('students.index')->with('success', 'Student added successfully.');
    }

    public function show(Student $student): Response
    {
        $student->load(['enrollments.section.subject', 'enrollments.semester']);

        return Inertia::render('students/Show', [
            'student' => $student,
        ]);
    }

    public function edit(Student $student): Response
    {
        return Inertia::render('students/Form', [
            'student' => $student,
        ]);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'student_no' => 'required|string|max:30|unique:students,student_no,'.$student->id,
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:students,email,'.$student->id,
            'course' => 'required|string|max:100',
            'year_level' => 'required|integer|min:1|max:6',
        ]);

        $student->update($validated);

        return redirect()->route('students.show', $student)->with('success', 'Student updated.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $student->delete();

        return redirect()->route('students.index')->with('success', 'Student removed.');
    }

    public function search(Request $request, Section $section): \Illuminate\Http\JsonResponse
    {
        $q = trim($request->get('q', ''));

        if (strlen($q) < 1) {
            return response()->json([]);
        }

        $alreadyEnrolledIds = Enrollment::where('section_id', $section->id)
            ->pluck('student_id');

        $students = Student::whereNotIn('id', $alreadyEnrolledIds)
            ->where(function ($query) use ($q) {
                $query->where('student_no', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%");
            })
            ->orderBy('last_name')
            ->limit(10)
            ->get(['id', 'student_no', 'first_name', 'last_name', 'course', 'year_level']);

        return response()->json($students);
    }

    public function enroll(Request $request, Section $section): RedirectResponse
    {
        $validated = $request->validate([
            'student_no' => 'required|string|exists:students,student_no',
        ]);

        $student = Student::where('student_no', $validated['student_no'])->firstOrFail();

        $alreadyEnrolled = Enrollment::where('student_id', $student->id)
            ->where('section_id', $section->id)
            ->exists();

        if ($alreadyEnrolled) {
            return back()->withErrors(['student_no' => "{$student->first_name} {$student->last_name} is already enrolled in this section."]);
        }

        Enrollment::create([
            'student_id' => $student->id,
            'section_id' => $section->id,
            'semester_id' => $section->semester_id,
            'status' => 'active',
        ]);

        return back()->with('success', "{$student->first_name} {$student->last_name} enrolled successfully.");
    }

    public function unenroll(Enrollment $enrollment): RedirectResponse
    {
        $sectionId = $enrollment->section_id;
        $enrollment->delete();

        return redirect()->route('sections.show', $sectionId)->with('success', 'Student removed from section.');
    }
}
