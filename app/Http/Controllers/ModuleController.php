<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\ModuleFile;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

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
                $path = $file->store("modules/{$module->id}");
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
                $path = $file->store("modules/{$module->id}");
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
            Storage::delete($file->file_path);
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
        Storage::disk('public')->delete($moduleFile->file_path);
        $sectionId = $moduleFile->module->section_id;
        $moduleId = $moduleFile->module_id;
        $moduleFile->delete();

        return redirect()
            ->route('modules.edit', $moduleId)
            ->with('success', 'File removed.');
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
