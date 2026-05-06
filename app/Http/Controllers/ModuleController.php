<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\ModuleFile;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ModuleController extends Controller
{
    public function index(Section $section): Response
    {
        $section->load(['subject', 'semester']);
        $modules = $section->modules()->with('files')->get();

        return Inertia::render('modules/Index', [
            'section' => $section,
            'modules' => $modules,
        ]);
    }

    public function create(Section $section): Response
    {
        $section->load(['subject', 'semester']);

        return Inertia::render('modules/Form', [
            'section' => $section,
        ]);
    }

    private function fileDisk(): string
    {
        return config('filesystems.default') === 's3' ? 's3' : 'public';
    }

    public function store(Request $request, Section $section): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'week_number' => 'nullable|integer|min:1|max:52',
            'is_published' => 'boolean',
            'files' => 'nullable|array|max:10',
            'files.*' => 'file|max:51200|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,zip,jpg,jpeg,png,gif,webp',
        ]);

        $nextOrder = $section->modules()->max('order') + 1;

        $module = $section->modules()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'week_number' => $validated['week_number'] ?? null,
            'order' => $nextOrder,
            'is_published' => $validated['is_published'] ?? false,
        ]);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store("modules/{$module->id}", $this->fileDisk());
                $module->files()->create([
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        return redirect()
            ->route('sections.modules.index', $section)
            ->with('success', 'Module created successfully.');
    }

    public function show(Module $module): Response
    {
        $module->load(['files', 'section.subject', 'section.semester']);

        return Inertia::render('modules/Show', [
            'module' => $module,
        ]);
    }

    public function edit(Module $module): Response
    {
        $module->load(['files', 'section.subject', 'section.semester']);

        return Inertia::render('modules/Form', [
            'module' => $module,
            'section' => $module->section,
        ]);
    }

    public function update(Request $request, Module $module): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'week_number' => 'nullable|integer|min:1|max:52',
            'is_published' => 'boolean',
            'files' => 'nullable|array|max:10',
            'files.*' => 'file|max:51200|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,zip,jpg,jpeg,png,gif,webp',
        ]);

        $module->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'week_number' => $validated['week_number'] ?? null,
            'is_published' => $validated['is_published'] ?? false,
        ]);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store("modules/{$module->id}", $this->fileDisk());
                $module->files()->create([
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        return redirect()
            ->route('sections.modules.index', $module->section_id)
            ->with('success', 'Module updated successfully.');
    }

    public function destroy(Module $module): RedirectResponse
    {
        $sectionId = $module->section_id;

        foreach ($module->files as $file) {
            Storage::disk($this->fileDisk())->delete($file->file_path);
        }

        $module->delete();

        return redirect()
            ->route('sections.modules.index', $sectionId)
            ->with('success', 'Module deleted.');
    }

    public function togglePublish(Module $module): RedirectResponse
    {
        $module->update(['is_published' => ! $module->is_published]);

        $status = $module->is_published ? 'published' : 'set to draft';

        return back()->with('success', "\"{$module->title}\" {$status}.");
    }

    public function destroyFile(ModuleFile $moduleFile): RedirectResponse
    {
        Storage::disk($this->fileDisk())->delete($moduleFile->file_path);
        $sectionId = $moduleFile->module->section_id;
        $moduleId = $moduleFile->module_id;
        $moduleFile->delete();

        return redirect()
            ->route('modules.edit', $moduleId)
            ->with('success', 'File removed.');
    }

    public function serveFile(Request $request, ModuleFile $moduleFile): StreamedResponse|RedirectResponse
    {
        $module = $moduleFile->module;
        $user   = $request->user();

        // Students: must be enrolled and module must be published
        if ($user->isStudent()) {
            $student  = $user->student;
            $enrolled = $student?->enrollments()
                ->where('section_id', $module->section_id)
                ->where('status', 'active')
                ->exists();

            if (! $enrolled || ! $module->is_published) {
                abort(403);
            }
        }

        $disk = $this->fileDisk();

        // S3 / R2: redirect to a short-lived signed URL
        if ($disk === 's3') {
            /** @var \Illuminate\Filesystem\FilesystemAdapter $s3 */
            $s3 = Storage::disk('s3');
            return redirect($s3->temporaryUrl($moduleFile->file_path, now()->addMinutes(30)));
        }

        // Local public disk: stream with inline disposition so browser previews it
        $absolutePath = Storage::disk('public')->path($moduleFile->file_path);

        if (! file_exists($absolutePath)) {
            abort(404);
        }

        $mime     = $moduleFile->file_type ?: mime_content_type($absolutePath) ?: 'application/octet-stream';
        $filename = $moduleFile->file_name;

        return response()->streamDownload(function () use ($absolutePath) {
            readfile($absolutePath);
        }, $filename, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'attachment; filename="' . addslashes($filename) . '"',
            'Cache-Control'       => 'private, max-age=3600',
        ]);
    }

    public function reorder(Request $request, Section $section): RedirectResponse
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:modules,id',
        ]);

        foreach ($validated['order'] as $position => $moduleId) {
            $section->modules()->where('id', $moduleId)->update(['order' => $position]);
        }

        return back()->with('success', 'Modules reordered.');
    }
}
