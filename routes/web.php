<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AiSettingsController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AttendanceRecordController;
use App\Http\Controllers\AttendanceSessionController;
use App\Http\Controllers\ClassRecordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\GradingComponentController;
use App\Http\Controllers\GradingController;
use App\Http\Controllers\GradingItemController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PlagiarismController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SemesterController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentModuleController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\TransmutationController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('module-files/{id}/serve',[ModuleController::class, 'serveFile'])->name('modules.files.serve');
});

// ─── Faculty & Admin ─────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified', 'role:faculty,admin'])->group(function () {

    // Subjects
    Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects');
    Route::get('/subjects/data', [SubjectController::class, 'subjects_data'])->name('subjects.data');
    Route::get('/subjects/create', [SubjectController::class, 'create'])->name('subjects.create');
    Route::post('/subjects/store', [SubjectController::class, 'store'])->name('subjects.store');
    Route::get('/subjects/edit/{id}', [SubjectController::class, 'edit'])->name('subjects.edit');
    Route::put('/subjects/update/{id}', [SubjectController::class, 'update'])->name('subjects.update');
    Route::delete('/subjects/delete/{id}', [SubjectController::class, 'destroy'])->name('subjects.destroy');

    // Sections
    Route::get('/sections', [SectionController::class, 'index'])->name('sections.index');
    Route::get('/sections/data', [SectionController::class, 'sections_data'])->name('sections.data');
    Route::get('/sections/form-data', [SectionController::class, 'form_data'])->name('sections.form-data');
    Route::get('/sections/create', [SectionController::class, 'create'])->name('sections.create');
    Route::post('/sections/store', [SectionController::class, 'store'])->name('sections.store');
    Route::get('/sections/show/{id}', [SectionController::class, 'show'])->name('sections.show');
    Route::get('/sections/data/{id}', [SectionController::class, 'section_data'])->name('sections.section-data');
    Route::get('/sections/edit/{id}', [SectionController::class, 'edit'])->name('sections.edit');
    Route::put('/sections/update/{id}', [SectionController::class, 'update'])->name('sections.update');
    Route::delete('/sections/delete/{id}', [SectionController::class, 'destroy'])->name('sections.destroy');

    //Enrollment
    Route::get( 'sections/{id}/students/search', [StudentController::class, 'search'] )->name('sections.students.search');
    Route::post( 'sections/{id}/enroll', [StudentController::class, 'enroll'] )->name('sections.enroll');
    Route::post( 'sections/{id}/bulk-enroll', [StudentController::class, 'bulkEnroll'] )->name('sections.bulk-enroll');

    //Module
    Route::get('sections/{sectionId}/modules',[ModuleController::class, 'index'])->name('sections.modules.index');
    Route::get('sections/{sectionId}/modules/create',[ModuleController::class, 'create'])->name('sections.modules.create');
    Route::post('sections/{sectionId}/modules',[ModuleController::class, 'store'])->middleware('throttle:uploads')->name('sections.modules.store');
    Route::post('sections/{sectionId}/modules/reorder',[ModuleController::class, 'reorder'])->name('sections.modules.reorder');
    Route::get('modules/{id}',[ModuleController::class, 'show'])->name('modules.show');
    Route::get('modules/{id}/edit',[ModuleController::class, 'edit'])->name('modules.edit');
    Route::put('modules/{id}',[ModuleController::class, 'update'])->middleware('throttle:uploads')->name('modules.update');
    Route::delete('modules/{id}',[ModuleController::class, 'destroy'])->name('modules.destroy');
    Route::post('modules/{id}/toggle-publish',[ModuleController::class, 'togglePublish'])->name('modules.toggle-publish');
    Route::delete('module-files/{id}',[ModuleController::class, 'destroyFile'])->name('module-files.destroy');
    
    
    // Assignments
    Route::get('sections/{sectionId}/assignments',[AssignmentController::class, 'index'])->name('sections.assignments.index');
    Route::get('sections/{sectionId}/assignments/data',[AssignmentController::class, 'data'])->name('sections.assignments.data');
    Route::get('sections/{sectionId}/assignments/create', [AssignmentController::class, 'create'])->name('sections.assignments.create');
    Route::post('sections/{sectionId}/assignments/import-pdf', [AssignmentController::class, 'importPdf'])->middleware('throttle:uploads')->name('sections.assignments.import-pdf');
    Route::post('sections/{sectionId}/assignments', [AssignmentController::class, 'store'])->name('sections.assignments.store');
    Route::get('assignments/{id}', [AssignmentController::class, 'show'])->name('assignments.show');
    Route::get('assignments/{id}/submissions/data',[AssignmentController::class, 'submissionsData'])->name('assignments.submissions.data');
    Route::get('assignments/{id}/edit', [AssignmentController::class, 'edit'])->name('assignments.edit');
    Route::put('assignments/{id}', [AssignmentController::class, 'update'])->name('assignments.update');
    Route::delete('assignments/{id}', [AssignmentController::class, 'destroy'])->name('assignments.destroy');
    Route::post('assignments/{id}/toggle-publish', [AssignmentController::class, 'togglePublish'])->name('assignments.toggle-publish');

    // Grading
    Route::get('submissions/{id}/grade', [GradingController::class, 'show'])->name('submissions.grade');
    Route::get('submissions/{id}/grade/data', [GradingController::class, 'data'])->name('submissions.grade.data');
    Route::post('submissions/{id}/approve', [GradingController::class, 'approve'])->name('submissions.approve');
    Route::post('grades/release', [GradingController::class, 'releaseGrades'])->name('grades.release');

    // Plagiarism
    Route::get('assignments/{assignmentId}/plagiarism', [PlagiarismController::class, 'show'])->name('assignments.plagiarism');
    Route::get('assignments/{assignmentId}/plagiarism/data', [PlagiarismController::class, 'data'])->name('assignments.plagiarism.data');
    Route::post('assignments/{assignmentId}/plagiarism/run', [PlagiarismController::class, 'run'])->middleware('throttle:ai')->name('assignments.plagiarism.run');

    // Students
    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/data', [StudentController::class, 'students_data'])->name('students.data');
    Route::get('/students/create', [StudentController::class, 'create'])->name('students.create');
    Route::post('/students/store', [StudentController::class, 'store'])->name('students.store');
    Route::get('/students/{id}', [StudentController::class, 'show'])->name('students.show');
    Route::get('/students/edit/{id}', [StudentController::class, 'edit'])->name('students.edit');
    Route::put('/students/update/{id}', [StudentController::class, 'update'])->name('students.update');
    Route::delete('/students/delete/{id}', [StudentController::class, 'destroy'])->name('students.destroy');

    Route::delete('enrollments/{id}', [StudentController::class, 'unenroll'])->name('enrollments.destroy');

    // Class Record
    Route::get('sections/{sectionId}/class-record',[ClassRecordController::class, 'page'])->name('sections.class-record');
    Route::get('sections/{sectionId}/class-record/data',[ClassRecordController::class, 'show'])->name('sections.class-record.data');
    Route::put('class-record/grades',[ClassRecordController::class, 'updateGrade'])->name('class-record.grades.update');
    Route::put('class-record/item-scores',[ClassRecordController::class, 'updateItemScore'])->name('class-record.item-scores.update');
    Route::post('sections/{sectionId}/class-record/release-all',[ClassRecordController::class, 'releaseAll'])->name('sections.class-record.release-all');
    Route::get(
        'sections/{sectionId}/class-record/pdf',
        [ClassRecordController::class, 'exportPdf']
    )->name('class-record.pdf');

    // Grading Components
    Route::get('sections/{sectionId}/components', [GradingComponentController::class, 'index'])->name('sections.components');
    Route::get('sections/{sectionId}/components/data', [GradingComponentController::class, 'data'])->name('sections.components.data');
    Route::post('sections/{sectionId}/components', [GradingComponentController::class, 'store'])->name('sections.components.store');
    Route::put('components/{componentId}', [GradingComponentController::class, 'update'])->name('components.update');
    Route::delete('components/{componentId}', [GradingComponentController::class, 'destroy'])->name('components.destroy');
    Route::post('components/{componentId}/toggle-lock', [GradingComponentController::class, 'toggleLock'])->name('components.toggle-lock');

    // Grading Items (per component, e.g. Quiz 1, Quiz 2...)
    Route::get('sections/{sectionId}/items', [GradingItemController::class, 'index'])->name('sections.items');
    Route::get('sections/{sectionId}/items/data', [GradingItemController::class, 'data'])->name('sections.items.data');
    Route::post('sections/{sectionId}/items', [GradingItemController::class, 'store'])->name('sections.items.store');
    Route::put('items/{itemId}', [GradingItemController::class, 'update'])->name('items.update');
    Route::delete('items/{itemId}', [GradingItemController::class, 'destroy'])->name('items.destroy');
    Route::post('items/{itemId}/toggle', [GradingItemController::class, 'toggle'])->name('items.toggle');

    // Transmutation
    Route::get('sections/{sectionId}/transmutation', [TransmutationController::class, 'index'])->name('sections.transmutation');
    Route::get('sections/{sectionId}/transmutation/data', [TransmutationController::class, 'data'])->name('sections.transmutation.data');
    Route::post('sections/{sectionId}/transmutation', [TransmutationController::class, 'store'])->name('sections.transmutation.store');
    Route::post('sections/{sectionId}/transmutation/default', [TransmutationController::class, 'useDefault'])->name('sections.transmutation.default');
    Route::delete('sections/{sectionId}/transmutation', [TransmutationController::class, 'reset'])->name('sections.transmutation.reset');
    // Attendance

    // Attendance - Index Page
    Route::get(
        'sections/{sectionId}/attendance',
        [AttendanceSessionController::class, 'page']
    )->name('attendance.index');

    // Attendance - Index Data
    Route::get(
        'sections/{sectionId}/attendance/data',
        [AttendanceSessionController::class, 'index']
    )->name('attendance.data');

    // Attendance - Summary Page
    Route::get(
        'sections/{sectionId}/attendance/summary',
        [AttendanceSessionController::class, 'summaryPage']
    )->name('attendance.summary');

    // Attendance - Summary Data
    Route::get(
        'sections/{sectionId}/attendance/summary/data',
        [AttendanceSessionController::class, 'summary']
    )->name('attendance.summary.data');

    // Attendance - Create Session
    Route::post(
        'sections/{sectionId}/attendance',
        [AttendanceSessionController::class, 'store']
    )->name('attendance.store');

    // Attendance - Session Page
    Route::get(
        'sections/{sectionId}/attendance/{sessionId}',
        [AttendanceSessionController::class, 'sessionPage']
    )->name('attendance.sessionPage');

    // Attendance - Session Data
    Route::get(
        'sections/{sectionId}/attendance/{sessionId}/data',
        [AttendanceSessionController::class, 'show']
    )->name('attendance.session.data');

    // Attendance - Close Session
    Route::post(
        'attendance-sessions/{sessionId}/close',
        [AttendanceSessionController::class, 'close']
    )->name('attendance.close');

    // Attendance - Delete Session
    Route::delete(
        'attendance-sessions/{sessionId}',
        [AttendanceSessionController::class, 'destroy']
    )->name('attendance.destroy');

    // Attendance Records - Bulk Update
    Route::post(
        'attendance-sessions/{sessionId}/bulk',
        [AttendanceRecordController::class, 'bulkUpdate']
    )->name('attendance.bulk');

    // Attendance Records - Mark All
    Route::post(
        'attendance-sessions/{sessionId}/mark-all',
        [AttendanceRecordController::class, 'markAll']
    )->name('attendance.mark-all');

    // Attendance - Summary PDF
    Route::get(
        'sections/{sectionId}/attendance/summary/pdf',
        [AttendanceSessionController::class, 'exportSummaryPdf']
    )->name('attendance.summary.pdf');
});

// ─── Student ──────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified', 'role:student'])->group(function () {
    Route::get('my-sections', [StudentModuleController::class, 'dashboard'])->name('student.dashboard');
    Route::get('my-sections/browse', [StudentModuleController::class, 'browseSections'])->name('student.browse');
    Route::post('my-sections/{id}/self-enroll', [StudentModuleController::class, 'selfEnroll'])->name('student.self-enroll');
    Route::get('my-sections/{id}', [StudentModuleController::class, 'sectionModules'])->name('student.section');
    Route::get('my-sections/{sectionId}/modules/{moduleId}', [StudentModuleController::class, 'viewModule'])->name('student.module');
    Route::post('modules/{id}/mark-read', [StudentModuleController::class, 'markRead'])->name('modules.mark-read');
    Route::get('my-sections/{id}/grades', [StudentModuleController::class, 'grades'])->name('student.grades');

    Route::get('my-sections/{sectionId}/assignments', [SubmissionController::class, 'studentAssignments'])->name('student.submissions');
    Route::get('assignments/{assignmentId}/submit', [SubmissionController::class, 'create'])->name('assignments.submit');
    Route::post('assignments/{assignmentId}/start', [SubmissionController::class, 'startExam'])->name('assignments.start');
    Route::post('assignments/{assignmentId}/submit', [SubmissionController::class, 'store'])->middleware('throttle:ai')->name('assignments.submit.store');
    Route::post('submissions/{id}/proctoring-events', [SubmissionController::class, 'recordProctoringEvent'])->name('submissions.proctoring-events');
    Route::get('submissions/{id}', [SubmissionController::class, 'show'])->name('submissions.show');
});

// ─── Admin Only ───────────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/dashboard/data', [AdminController::class, 'dashboardData'])->name('dashboard.data');

    Route::get('/users', [AdminController::class, 'index'])->name('users');
    Route::get('/users/data', [AdminController::class, 'users'])->name('users.list');
    Route::put('/users/{user}/role', [AdminController::class, 'updateRole'])->name('users.role');
    
    Route::get('reports', [AdminController::class, 'reports_index'])->name('reports');
    Route::get('reports/data', [AdminController::class, 'reports'])->name('reports.data');

    Route::get('ai-settings', [AiSettingsController::class, 'edit'])
        ->name('ai-settings.edit');

    Route::get('ai-settings/data', [AiSettingsController::class, 'data'])
        ->name('ai-settings.data');

    Route::put('ai-settings', [AiSettingsController::class, 'update'])
        ->name('ai-settings.update');

    Route::put('ai-settings/providers/{id}', [AiSettingsController::class, 'updateProvider'])
        ->name('ai-settings.providers.update');

    Route::put('ai-settings/providers/{id}/toggle', [AiSettingsController::class, 'toggleProvider'])
        ->name('ai-settings.providers.toggle');

    Route::post('ai-settings/providers/{id}/check', [AiSettingsController::class, 'checkProvider'])
        ->name('ai-settings.providers.check');
        
    // Semesters — admin only
    Route::get('/semesters', [SemesterController::class, 'index'])
        ->name('semesters.index');

    Route::get('/semesters/data', [SemesterController::class, 'semesters_data'])
        ->name('semesters.data');

    Route::get('/semesters/create', [SemesterController::class, 'create'])
        ->name('semesters.create');

    Route::post('/semesters/store', [SemesterController::class, 'store'])
        ->name('semesters.store');

    Route::get('/semesters/edit/{id}', [SemesterController::class, 'edit'])
        ->name('semesters.edit');

    Route::put('/semesters/update/{id}', [SemesterController::class, 'update'])
        ->name('semesters.update');

    Route::delete('/semesters/delete/{id}', [SemesterController::class, 'destroy'])
        ->name('semesters.destroy');

    Route::put('/semesters/set-active/{id}', [SemesterController::class, 'setActive'])
        ->name('semesters.setActive');
    });

Route::get('/health', function () {
    return 'OK';
});

Route::post('/forgot-password/verify', [ForgotPasswordController::class, 'verify'])
    ->name('password.verify');

Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'reset'])
    ->name('password.reset.custom');

require __DIR__.'/settings.php';
