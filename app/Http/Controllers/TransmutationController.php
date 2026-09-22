<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\TransmutationScale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;

class TransmutationController extends Controller
{
    /**
     * Render the transmutation page.
     */
    public function index(string $sectionId): Response
    {
        $section = $this->resolveSection($sectionId);

        return Inertia::render('class-record/Transmutation', [
            'sectionId' => $this->encryptId($section->id),
        ]);
    }

    /**
     * Load transmutation data for Axios.
     */
    public function data(string $sectionId): JsonResponse
    {
        $section = $this->resolveSection($sectionId);

        $scale = TransmutationScale::query()
            ->where('section_id', $section->id)
            ->orderByDesc('min_score')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Transmutation scale loaded successfully.',
            'data' => [
                'section' => $this->transformSection($section),

                'scale' => $scale
                    ->map(fn ($row) => $this->transformScale($row))
                    ->values(),

                'default_scale' => collect(
                    TransmutationScale::defaultScale()
                )->values(),
            ],
        ]);
    }

    /**
     * Apply the default Philippine grading scale.
     */
    public function useDefault(string $sectionId): JsonResponse
    {
        $section = $this->resolveSection($sectionId);

        TransmutationScale::query()
            ->where('section_id', $section->id)
            ->delete();

        foreach (TransmutationScale::defaultScale() as $row) {
            TransmutationScale::query()->create([
                'section_id' => $section->id,
                'min_score' => $row['min'],
                'max_score' => $row['max'],
                'grade' => $row['grade'],
                'description' => $row['description'],
            ]);
        }

        $scale = TransmutationScale::query()
            ->where('section_id', $section->id)
            ->orderByDesc('min_score')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Default Philippine grading scale applied.',
            'data' => [
                'scale' => $scale
                    ->map(fn ($row) => $this->transformScale($row))
                    ->values(),
            ],
        ]);
    }

    /**
     * Save a custom transmutation scale.
     */
    public function store(
        Request $request,
        string $sectionId
    ): JsonResponse {
        $validated = $request->validate([
            'rows' => 'required|array|min:1',

            'rows.*.min_score' =>
                'required|numeric|min:0|max:100',

            'rows.*.max_score' =>
                'required|numeric|min:0|max:100|gte:rows.*.min_score',

            'rows.*.grade' =>
                'required|string|max:10',

            'rows.*.description' =>
                'nullable|string|max:50',
        ]);

        $section = $this->resolveSection($sectionId);

        TransmutationScale::query()
            ->where('section_id', $section->id)
            ->delete();

        foreach ($validated['rows'] as $row) {
            TransmutationScale::query()->create([
                'section_id' => $section->id,
                'min_score' => $row['min_score'],
                'max_score' => $row['max_score'],
                'grade' => $row['grade'],
                'description' => $row['description'] ?? null,
            ]);
        }

        $scale = TransmutationScale::query()
            ->where('section_id', $section->id)
            ->orderByDesc('min_score')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Transmutation scale saved.',
            'data' => [
                'scale' => $scale
                    ->map(fn ($row) => $this->transformScale($row))
                    ->values(),
            ],
        ]);
    }

    /**
     * Remove the custom transmutation scale.
     */
    public function reset(string $sectionId): JsonResponse
    {
        $section = $this->resolveSection($sectionId);

        TransmutationScale::query()
            ->where('section_id', $section->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Custom scale removed. Default scale is now active.',
        ]);
    }

    /**
     * Resolve a section from an encrypted ID.
     */
    private function resolveSection(string $encryptedId): Section
    {
        $id = $this->decryptId($encryptedId);

        return Section::query()
            ->with([
                'subject',
                'semester',
            ])
            ->findOrFail($id);
    }

    /**
     * Transform a section for the JSON response.
     */
    private function transformSection(Section $section): array
    {
        return [
            'id' => $this->encryptId($section->id),
            'name' => $section->name,

            'subject' => $section->subject
                ? [
                    'id' => $this->encryptId($section->subject->id),
                    'code' => $section->subject->code,
                    'name' => $section->subject->name,
                ]
                : null,

            'semester' => $section->semester
                ? [
                    'id' => $this->encryptId($section->semester->id),
                    'name' => $section->semester->name,
                ]
                : null,
        ];
    }

    /**
     * Transform a transmutation scale row.
     */
    private function transformScale(
        TransmutationScale $scale
    ): array {
        return [
            'id' => $this->encryptId($scale->id),
            'section_id' => $this->encryptId($scale->section_id),
            'min_score' => $scale->min_score,
            'max_score' => $scale->max_score,
            'grade' => $scale->grade,
            'description' => $scale->description,
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
}