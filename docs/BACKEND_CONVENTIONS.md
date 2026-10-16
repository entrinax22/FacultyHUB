# FacultyHUB Backend Conventions

Verified from representative controllers, requests, middleware, models, jobs, routes, services, and migrations. Future backend changes should read this document and the relevant implementation first.

## Established architecture

- Laravel controllers are the application boundary. They commonly contain small private resolver/transform helpers rather than repositories or DTOs. Examples: `app/Http/Controllers/AssignmentController.php`, `SubmissionController.php`, and `AttendanceSessionController.php`.
- Browser pages use Inertia responses such as `Inertia::render('assignments/Index', [...])`; interactive data mutations and table loading use Axios-compatible JSON endpoints. See `AssignmentController.php:104-148,215-271` and `AttendanceSessionController.php:23-73`.
- Routes are grouped by authentication and role: `auth,verified` for shared pages, `role:faculty,admin` plus `faculty.resource` for faculty/admin resources, `role:student` for student resources, and `role:admin` plus `admin.2fa` for administration (`routes/web.php:70-76,266,288`).
- Route names follow resource/action names such as `sections.modules.index`, `assignments.update`, and `attendance.bulk`. Preserve existing names and URLs.

## Controllers and responses

- Use explicit return types where established: `Response` for Inertia, `JsonResponse` for Axios endpoints, and `RedirectResponse` for redirects.
- JSON responses use a stable envelope: `{ success: true|false, message: string, data: ... }`; validation errors are allowed to use Laravel's normal validation response. Examples: `AttendanceSessionController.php:73-84`, `PlagiarismController.php:40-68`, and `SubmissionController.php:480-500`.
- Successful mutations generally return HTTP 200 unless a created resource/event is being reported; errors use 4xx for validation/state/authorization and a generic 500 message after reporting unexpected exceptions. Do not expose exception details.
- Controllers commonly use `try/catch` around JSON workflows, rethrow `ValidationException` and HTTP exceptions, call `report($e)`, then return a generic JSON failure. Preserve that contract when modifying comparable endpoints.
- Use `DB::transaction()` for multi-table writes, as in assignment create/update flows (`AssignmentController.php:520-556,572-629`).

## Validation and data access

- Simple endpoint validation uses `$request->validate([...])`; reusable settings validation uses Form Requests such as `ProfileUpdateRequest` and `PasswordUpdateRequest`.
- Models use `$fillable`, relationship methods, casts, and small domain predicates (`app/Models/Section.php`, `Submission.php`). Use relationships and eager loading (`with`) where existing transforms require related records.
- Paginate searchable/table data rather than returning all rows: `AssignmentController.php:248,431` and `AdminController.php:175`. Preserve the frontend pagination shape when changing these queries.
- Existing list/report endpoints also use `get()` for bounded dashboard/report data. Treat unbounded `get()` on user/submission collections as a performance risk and add pagination/chunking when the enhancement requires it.
- Migrations use Laravel `Schema` definitions, foreign keys, cascade/null-on-delete behavior, composite uniqueness, and targeted indexes. See submission/feedback/plagiarism migrations and `2026_05_06_083929_add_performance_indexes.php`.

## Identifiers and authorization

- Client-facing database identifiers are generally encrypted with `Crypt::encryptString((string) $id)` and decoded in controller or middleware helpers. Decryption failures should become 404, not an exception leak (`EnsureFacultyResourceAccess.php:75-83`).
- Encryption is an identifier-obfuscation/transport convention, not authorization. Ownership is enforced through role middleware plus section ownership resolution (`EnsureFacultyResourceAccess.php:19-65`), and attendance enrollment is checked in the attendance controller.
- Preserve 403 for authenticated-but-forbidden actions and 404 where an invalid/unauthorized resource should not be disclosed. New mutations must verify the owning section/resource at the request boundary.
- The middleware-based ownership approach is established, but its route-name mapping and body-driven class-record/grading paths require focused tests whenever those routes change.

## Services, jobs, and external work

- Services are used for cross-controller/domain work already shared by the application, e.g. `AIGraderService` and `StudentNotificationService`; do not introduce a repository/service layer without a concrete reuse or testability need.
- Long-running AI grading and plagiarism work is dispatched to `ShouldQueue` jobs (`app/Jobs/GradeEssayJob.php`, `GradeCodeJob.php`, `CheckPlagiarismJob.php`). Jobs declare retry/backoff or timeout values and use dependency injection in `handle()`.
- Queue jobs update submission/feedback state and report failures through `failed()`. Do not log secrets, full submissions, recovery codes, or provider payloads.
- AI calls use Laravel's HTTP client with a timeout and generic user-facing failures (`AIGraderService.php:284-323`). External calls should remain out of request paths where latency or reliability is material.

## Intentional conventions versus legacy inconsistencies

- The Inertia + Axios split, JSON envelope, encrypted IDs, role middleware, and transaction usage are intentional conventions.
- Controllers contain inconsistent formatting, very large transform methods, mixed `get()`/pagination, inline validation, and repeated encryption/response code. These are legacy patterns, not a reason to add broad abstractions during a focused change.
- Some route declarations have inconsistent spacing and some controllers use snake-case method names such as `subjects_data`; preserve public names for compatibility.
- Existing broad `try/catch` blocks and generic 500 responses improve user-facing safety but can obscure observability; improve them only with scoped, non-sensitive logging and tests.

## Enhancement readiness findings

- **High performance risk:** `CheckPlagiarismJob.php:21-38` loads every non-empty submission for an assignment and sends all content in one AI request. Large assignments can exceed memory, provider limits, or timeout budgets. Plan bounded batches/chunking and an explicit result strategy.
- **High security/data-integrity risk:** AI grading trusts provider-shaped arrays (`GradeEssayJob.php:23-47`, `GradeCodeJob.php:23-47`) with only an error check. Add schema/range validation before persisting scores and feedback; never treat model output as authoritative without teacher review.
- **Medium performance risk:** `AIGraderService` loads database-backed settings/provider state in its constructor (`AIGraderService.php:41-59`) and synchronous draft/import paths can invoke external AI work from a request. Confirm those paths before adding enhancements and queue expensive work.
- **Medium authorization risk:** Faculty ownership is centralized for encrypted route identifiers, but body-driven class-record/grade operations and student submission access are separate controller checks. Add regression tests for every mutation path before extending grading.
- **Medium operational risk:** Queue jobs exist, but deployment must run a worker against the configured queue backend (`config/queue.php:16`, `.env.example:38`). A deployed app without workers will leave grading/plagiarism state pending.
- **Medium query risk:** Several report/notification/plagiarism paths use unbounded `get()` and nested relationship transforms. Add pagination, limits, or chunking as data volume grows.
- **Low maintainability risk:** Large controllers duplicate identifier resolution and serialization. Prefer small private helpers or an existing service only when modifying the affected workflow; avoid a repository/DTO rewrite.

## Future change checklist

1. Read this document and the relevant controller/request/model/job/migration.
2. Preserve route names, Inertia page names, Axios JSON envelopes, encrypted-ID behavior, and status codes.
3. Validate input and resource ownership before writes; use transactions for related writes.
4. Queue external/long-running work and make job state transitions retry-safe.
5. Add focused feature tests for success, validation, authorization, and data integrity.
6. Run the narrowest relevant tests, syntax/type/lint checks, then the broader suite.
