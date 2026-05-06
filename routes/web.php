<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AttendanceRecordController;
use App\Http\Controllers\AttendanceSessionController;
use App\Http\Controllers\ClassRecordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GradingComponentController;
use App\Http\Controllers\GradingItemController;
use App\Http\Controllers\GradingController;
use App\Http\Controllers\TransmutationController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PlagiarismController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SemesterController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentModuleController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

// ─── Faculty & Admin ─────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified', 'role:faculty,admin'])->group(function () {

    // Semesters
    Route::resource('semesters', SemesterController::class);
    Route::post('semesters/{semester}/set-active', [SemesterController::class, 'setActive'])->name('semesters.set-active');

    // Subjects
    Route::resource('subjects', SubjectController::class);

    // Sections
    Route::resource('sections', SectionController::class);
    Route::get('sections/{section}/students/search', [StudentController::class, 'search'])->name('sections.students.search');
    Route::post('sections/{section}/enroll', [StudentController::class, 'enroll'])->name('sections.enroll');

    // Modules
    Route::get('sections/{section}/modules', [ModuleController::class, 'index'])->name('sections.modules.index');
    Route::get('sections/{section}/modules/create', [ModuleController::class, 'create'])->name('sections.modules.create');
    Route::post('sections/{section}/modules', [ModuleController::class, 'store'])->middleware('throttle:uploads')->name('sections.modules.store');
    Route::post('sections/{section}/modules/reorder', [ModuleController::class, 'reorder'])->name('sections.modules.reorder');
    Route::get('modules/{module}', [ModuleController::class, 'show'])->name('modules.show');
    Route::get('modules/{module}/edit', [ModuleController::class, 'edit'])->name('modules.edit');
    Route::put('modules/{module}', [ModuleController::class, 'update'])->middleware('throttle:uploads')->name('modules.update');
    Route::delete('modules/{module}', [ModuleController::class, 'destroy'])->name('modules.destroy');
    Route::post('modules/{module}/toggle-publish', [ModuleController::class, 'togglePublish'])->name('modules.toggle-publish');
    Route::delete('module-files/{moduleFile}', [ModuleController::class, 'destroyFile'])->name('module-files.destroy');

    // Assignments
    Route::get('sections/{section}/assignments', [AssignmentController::class, 'index'])->name('sections.assignments.index');
    Route::get('sections/{section}/assignments/create', [AssignmentController::class, 'create'])->name('sections.assignments.create');
    Route::post('sections/{section}/assignments', [AssignmentController::class, 'store'])->name('sections.assignments.store');
    Route::get('assignments/{assignment}', [AssignmentController::class, 'show'])->name('assignments.show');
    Route::get('assignments/{assignment}/edit', [AssignmentController::class, 'edit'])->name('assignments.edit');
    Route::put('assignments/{assignment}', [AssignmentController::class, 'update'])->name('assignments.update');
    Route::delete('assignments/{assignment}', [AssignmentController::class, 'destroy'])->name('assignments.destroy');
    Route::post('assignments/{assignment}/toggle-publish', [AssignmentController::class, 'togglePublish'])->name('assignments.toggle-publish');

    // Grading
    Route::get('submissions/{submission}/grade', [GradingController::class, 'show'])->name('submissions.grade');
    Route::post('submissions/{submission}/approve', [GradingController::class, 'approve'])->name('submissions.approve');
    Route::post('grades/release', [GradingController::class, 'releaseGrades'])->name('grades.release');

    // Plagiarism
    Route::get('assignments/{assignment}/plagiarism', [PlagiarismController::class, 'show'])->name('assignments.plagiarism');
    Route::post('assignments/{assignment}/plagiarism/run', [PlagiarismController::class, 'run'])->middleware('throttle:ai')->name('assignments.plagiarism.run');

    // Students
    Route::resource('students', StudentController::class);
    Route::delete('enrollments/{enrollment}', [StudentController::class, 'unenroll'])->name('enrollments.destroy');

    // Class Record
    Route::get('sections/{section}/class-record', [ClassRecordController::class, 'show'])->name('sections.class-record');
    Route::post('grades/update', [ClassRecordController::class, 'updateGrade'])->name('grades.update');
    Route::post('item-scores/update', [ClassRecordController::class, 'updateItemScore'])->name('item-scores.update');
    Route::post('sections/{section}/class-record/release', [ClassRecordController::class, 'releaseAll'])->name('sections.class-record.release');

    // Grading Components
    Route::get('sections/{section}/components', [GradingComponentController::class, 'index'])->name('sections.components');
    Route::post('sections/{section}/components', [GradingComponentController::class, 'store'])->name('sections.components.store');
    Route::put('components/{component}', [GradingComponentController::class, 'update'])->name('components.update');
    Route::delete('components/{component}', [GradingComponentController::class, 'destroy'])->name('components.destroy');
    Route::post('components/{component}/toggle-lock', [GradingComponentController::class, 'toggleLock'])->name('components.toggle-lock');

    // Grading Items (per component, e.g. Quiz 1, Quiz 2...)
    Route::get('sections/{section}/items', [GradingItemController::class, 'index'])->name('sections.items');
    Route::post('sections/{section}/items', [GradingItemController::class, 'store'])->name('sections.items.store');
    Route::put('items/{item}', [GradingItemController::class, 'update'])->name('items.update');
    Route::delete('items/{item}', [GradingItemController::class, 'destroy'])->name('items.destroy');
    Route::post('items/{item}/toggle', [GradingItemController::class, 'toggle'])->name('items.toggle');

    // Transmutation
    Route::get('sections/{section}/transmutation', [TransmutationController::class, 'index'])->name('sections.transmutation');
    Route::post('sections/{section}/transmutation', [TransmutationController::class, 'store'])->name('sections.transmutation.store');
    Route::post('sections/{section}/transmutation/default', [TransmutationController::class, 'useDefault'])->name('sections.transmutation.default');
    Route::delete('sections/{section}/transmutation', [TransmutationController::class, 'reset'])->name('sections.transmutation.reset');

    // Attendance
    Route::get('sections/{section}/attendance', [AttendanceSessionController::class, 'index'])->name('attendance.index');
    Route::post('sections/{section}/attendance', [AttendanceSessionController::class, 'store'])->name('attendance.store');
    Route::get('sections/{section}/attendance/summary', [AttendanceSessionController::class, 'summary'])->name('attendance.summary');
    Route::get('sections/{section}/attendance/{session}', [AttendanceSessionController::class, 'show'])->name('attendance.session');
    Route::post('attendance-sessions/{session}/close', [AttendanceSessionController::class, 'close'])->name('attendance.close');
    Route::delete('attendance-sessions/{session}', [AttendanceSessionController::class, 'destroy'])->name('attendance.destroy');
    Route::post('attendance-sessions/{session}/bulk', [AttendanceRecordController::class, 'bulkUpdate'])->name('attendance.bulk');
    Route::post('attendance-sessions/{session}/mark-all', [AttendanceRecordController::class, 'markAll'])->name('attendance.mark-all');
});

// ─── Student ──────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified', 'role:student'])->group(function () {
    Route::get('my-sections', [StudentModuleController::class, 'dashboard'])->name('student.dashboard');
    Route::get('my-sections/{section}', [StudentModuleController::class, 'sectionModules'])->name('student.section');
    Route::get('my-sections/{section}/modules/{module}', [StudentModuleController::class, 'viewModule'])->name('student.module');
    Route::post('modules/{module}/mark-read', [StudentModuleController::class, 'markRead'])->name('modules.mark-read');

    Route::get('my-sections/{section}/grades', [StudentModuleController::class, 'grades'])->name('student.grades');
    Route::get('my-sections/{section}/assignments', [SubmissionController::class, 'studentAssignments'])->name('student.submissions');
    Route::get('assignments/{assignment}/submit', [SubmissionController::class, 'create'])->name('assignments.submit');
    Route::post('assignments/{assignment}/submit', [SubmissionController::class, 'store'])->middleware('throttle:ai')->name('assignments.submit.store');
    Route::get('submissions/{submission}', [SubmissionController::class, 'show'])->name('submissions.show');
});

// ─── Admin Only ───────────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('users', [AdminController::class, 'users'])->name('users');
    Route::put('users/{user}/role', [AdminController::class, 'updateRole'])->name('users.role');
    Route::get('reports', [AdminController::class, 'reports'])->name('reports');
});

require __DIR__.'/settings.php';
