<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesStudent;
use App\Models\Module;
use App\Models\ModuleFile;
use App\Models\Section;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ModuleController extends Controller
{
    use ResolvesStudent;

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function resolveSection(string $id): Section
    {
        try {
            $sectionId = Crypt::decryptString($id);

            return Section::findOrFail($sectionId);
        } catch (DecryptException $e) {
            abort(404);
        }
    }

    private function resolveModule(string $id): Module
    {
        try {
            $moduleId = Crypt::decryptString($id);

            return Module::findOrFail($moduleId);
        } catch (DecryptException $e) {
            abort(404);
        }
    }

    private function resolveModuleFile(string $id): ModuleFile
    {
        try {
            $fileId = Crypt::decryptString($id);

            return ModuleFile::findOrFail($fileId);
        } catch (DecryptException $e) {
            abort(404);
        }
    }

    private function fileDisk(): string
    {
        return config('filesystems.default') === 's3'
            ? 's3'
            : 'public';
    }

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    */

    public function index(string $sectionId): Response
    {
        $section = $this->resolveSection($sectionId);

        $section->load([
            'subject',
            'semester',
        ]);

        $modules = $section->modules()
            ->with('files')
            ->orderBy('order')
            ->get();

        return Inertia::render('modules/Index', [
            'section' => $this->transformSection($section),
            'modules' => $modules->map(
                fn ($module) => $this->transformModule($module)
            )->values()->all(),
        ]);
    }

    public function create(string $sectionId): Response
    {
        $section = $this->resolveSection($sectionId);

        $section->load([
            'subject',
            'semester',
        ]);

        return Inertia::render('modules/Form', [
            'section' => [
                'id' => Crypt::encryptString((string) $section->id),
                'name' => $section->name,
                'schedule' => $section->schedule,
                'subject' => $section->subject ? [
                    'id' => Crypt::encryptString(
                        (string) $section->subject->id
                    ),
                    'code' => $section->subject->code,
                    'name' => $section->subject->name,
                ] : null,
                'semester' => $section->semester ? [
                    'id' => Crypt::encryptString(
                        (string) $section->semester->id
                    ),
                    'name' => $section->semester->name,
                    'school_year' => $section->semester->school_year,
                ] : null,
            ],
        ]);
    }

    public function show(string $id): Response
    {
        $module = $this->resolveModule($id);

        $module->load([
            'files',
            'section.subject',
            'section.semester',
        ]);

        $moduleData = $this->transformModule($module);

        $moduleData['section'] = $this->transformSection($module->section);

        return Inertia::render('modules/Show', [
            'module' => $moduleData,
        ]);
    }

    public function edit(string $id): Response
    {
        $module = $this->resolveModule($id);

        $module->load([
            'files',
            'section.subject',
            'section.semester',
        ]);

        return Inertia::render('modules/Form', [
            'module' => $this->transformModule($module),
            'section' => $this->transformSection($module->section),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Data
    |--------------------------------------------------------------------------
    */

    public function modules_data(
        Request $request,
        string $sectionId
    ): JsonResponse {
        $section = $this->resolveSection($sectionId);

        $modules = $section->modules()
            ->with('files')
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        $modules = $modules->map(
            fn (Module $module) => $this->transformModule($module)
        );

        return response()->json([
            'success' => true,
            'message' => 'Modules retrieved successfully.',
            'data' => $modules,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request,
        string $sectionId
    ): JsonResponse {
        $section = $this->resolveSection($sectionId);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'week_number' => 'nullable|integer|min:1|max:52',
            'is_published' => 'boolean',
            'files' => 'nullable|array|max:10',
            'files.*' => [
                'file',
                'max:51200',
                'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,zip,jpg,jpeg,png,gif,webp',
            ],
        ]);

        $nextOrder = ($section->modules()->max('order') ?? 0) + 1;

        $module = $section->modules()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'week_number' => $validated['week_number'] ?? null,
            'order' => $nextOrder,
            'is_published' => $validated['is_published'] ?? false,
        ]);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store(
                    "modules/{$module->id}",
                    $this->fileDisk()
                );

                $module->files()->create([
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        $module->load([
            'files',
            'section.subject',
            'section.semester',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Module created successfully.',
            'data' => $this->transformModule($module),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        string $id
    ): JsonResponse {
        $module = $this->resolveModule($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'week_number' => 'nullable|integer|min:1|max:52',
            'is_published' => 'boolean',
            'files' => 'nullable|array|max:10',
            'files.*' => [
                'file',
                'max:51200',
                'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,zip,jpg,jpeg,png,gif,webp',
            ],
        ]);

        $module->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'week_number' => $validated['week_number'] ?? null,
            'is_published' => $validated['is_published'] ?? false,
        ]);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store(
                    "modules/{$module->id}",
                    $this->fileDisk()
                );

                $module->files()->create([
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        $module->load([
            'files',
            'section.subject',
            'section.semester',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Module updated successfully.',
            'data' => $this->transformModule($module),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    public function destroy(string $id): JsonResponse
    {
        $module = $this->resolveModule($id);

        foreach ($module->files as $file) {
            Storage::disk($this->fileDisk())
                ->delete($file->file_path);
        }

        $module->delete();

        return response()->json([
            'success' => true,
            'message' => 'Module deleted successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Publish
    |--------------------------------------------------------------------------
    */

    public function togglePublish(string $id): JsonResponse
    {
        $module = $this->resolveModule($id);

        $module->update([
            'is_published' => ! $module->is_published,
        ]);

        $status = $module->is_published
            ? 'published'
            : 'set to draft';

        return response()->json([
            'success' => true,
            'message' => "\"{$module->title}\" {$status}.",
            'data' => [
                'id' => Crypt::encryptString((string) $module->id),
                'is_published' => $module->is_published,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete File
    |--------------------------------------------------------------------------
    */

    public function destroyFile(string $id): JsonResponse
    {
        $moduleFile = $this->resolveModuleFile($id);

        Storage::disk($this->fileDisk())
            ->delete($moduleFile->file_path);

        $moduleFile->delete();

        return response()->json([
            'success' => true,
            'message' => 'File removed successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Serve File
    |--------------------------------------------------------------------------
    */

    public function serveFile(
        Request $request,
        string $id
    ): StreamedResponse|\Illuminate\Http\RedirectResponse {
        $moduleFile = $this->resolveModuleFile($id);

        $module = $moduleFile->module;
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Student Access
        |--------------------------------------------------------------------------
        */

        if ($user->isStudent()) {
            $student = $this->resolveStudent($request);

            $enrolled = $student->enrollments()
                ->where('section_id', $module->section_id)
                ->where('status', 'active')
                ->exists();

            if (! $enrolled || ! $module->is_published) {
                abort(403);
            }
        }

        $disk = $this->fileDisk();

        /*
        |--------------------------------------------------------------------------
        | S3 / R2
        |--------------------------------------------------------------------------
        */

        if ($disk === 's3') {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $s3 */
        $s3 = Storage::disk('s3');

        return redirect(
            $s3->temporaryUrl(
                $moduleFile->file_path,
                now()->addMinutes(30)
            )
        );
    }

        /*
        |--------------------------------------------------------------------------
        | Local Storage
        |--------------------------------------------------------------------------
        */

        $absolutePath = Storage::disk('public')
            ->path($moduleFile->file_path);

        if (! file_exists($absolutePath)) {
            abort(404);
        }

        $mime = $moduleFile->file_type
            ?: mime_content_type($absolutePath)
            ?: 'application/octet-stream';

        $filename = $moduleFile->file_name;

        return response()->streamDownload(
            function () use ($absolutePath) {
                readfile($absolutePath);
            },
            $filename,
            [
                'Content-Type' => $mime,
                'Content-Disposition' =>
                    'attachment; filename="' .
                    addslashes($filename) .
                    '"',
                'Cache-Control' => 'private, max-age=3600',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Reorder
    |--------------------------------------------------------------------------
    */

    public function reorder(
        Request $request,
        string $sectionId
    ): JsonResponse {
        $section = $this->resolveSection($sectionId);

        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'required|string',
        ]);

        foreach ($validated['order'] as $position => $encryptedModuleId) {
            try {
                $moduleId = Crypt::decryptString($encryptedModuleId);
            } catch (DecryptException $e) {
                continue;
            }

            $section->modules()
                ->where('id', $moduleId)
                ->update([
                    'order' => $position,
                ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Modules reordered successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Transformers
    |--------------------------------------------------------------------------
    */

    private function transformSection(Section $section): array
    {
        return [
            'id' => Crypt::encryptString((string) $section->id),
            'name' => $section->name,

            'subject' => [
                'id' => $section->subject
                    ? Crypt::encryptString((string) $section->subject->id)
                    : null,
                'code' => $section->subject?->code,
                'name' => $section->subject?->name,
            ],

            'semester' => [
                'id' => $section->semester
                    ? Crypt::encryptString((string) $section->semester->id)
                    : null,
                'name' => $section->semester?->name,
                'school_year' => $section->semester?->school_year,
            ],
        ];
    }

    private function transformModule(Module $module): array
    {
        return [
            'id' => Crypt::encryptString((string) $module->id),
            'title' => $module->title,
            'description' => $module->description,
            'week_number' => $module->week_number,
            'order' => $module->order,
            'is_published' => (bool) $module->is_published,

            'files' => $module->files->map(function ($file) {
                $encryptedId = Crypt::encryptString((string) $file->id);

                return [
                    'id' => $encryptedId,
                    'file_name' => $file->file_name,
                    'file_type' => $file->file_type,
                    'file_size' => $file->file_size,
                    'file_url' => route(
                        'modules.files.serve',
                        $encryptedId
                    ),
                ];
            })->values()->all(),
        ];
    }
}