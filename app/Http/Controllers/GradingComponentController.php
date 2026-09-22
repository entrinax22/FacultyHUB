<?php

namespace App\Http\Controllers;

use App\Models\GradingComponent;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;

class GradingComponentController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Index Page
    |--------------------------------------------------------------------------
    */

    public function index(string $sectionId): Response
    {
        $section = $this->resolveSection($sectionId);

        return Inertia::render('class-record/Components', [
            'sectionId' => $this->encryptId($section->id),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Data
    |--------------------------------------------------------------------------
    */

    public function data(string $sectionId): JsonResponse
    {
        $section = $this->resolveSection($sectionId);

        $section->load([
            'subject',
            'semester',
        ]);

        $components = $section
            ->gradingComponents()
            ->orderBy('order')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Grading components loaded successfully.',

            'data' => [
                'section' => [
                    'id' => $this->encryptId($section->id),
                    'name' => $section->name,

                    'subject' => $section->subject
                        ? [
                            'id' => $this->encryptId(
                                $section->subject->id
                            ),
                            'code' => $section->subject->code,
                            'name' => $section->subject->name,
                        ]
                        : null,

                    'semester' => $section->semester
                        ? [
                            'id' => $this->encryptId(
                                $section->semester->id
                            ),
                            'name' => $section->semester->name,
                        ]
                        : null,
                ],

                'components' => $components
                    ->map(fn ($component) =>
                        $this->transformComponent($component)
                    )
                    ->values(),

                'midtermWeight' => $components
                    ->where('period', 'midterm')
                    ->sum('weight_percentage'),

                'finalsWeight' => $components
                    ->where('period', 'finals')
                    ->sum('weight_percentage'),

                'generalWeight' => $components
                    ->whereNull('period')
                    ->sum('weight_percentage'),
            ],
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
        $validated = $request->validate([
            'name' => 'required|string|max:100',

            'weight_percentage' =>
                'required|numeric|min:0.01|max:100',

            'max_score' =>
                'required|numeric|min:1|max:100000',

            'period' =>
                'nullable|in:midterm,finals',
        ]);

        $section = $this->resolveSection($sectionId);

        $period = $validated['period'] ?? null;

        $current = $section
            ->gradingComponents()
            ->where('period', $period)
            ->sum('weight_percentage');

        $newWeight = (float) $validated['weight_percentage'];

        if ($current + $newWeight > 100.01) {
            $group = $period
                ? ucfirst($period)
                : 'General';

            return response()->json([
                'success' => false,
                'message' =>
                    "{$group} weights cannot exceed 100%. Currently used: {$current}%.",
            ], 422);
        }

        $maxOrder = $section
            ->gradingComponents()
            ->max('order');

        $order = $maxOrder !== null
            ? $maxOrder + 1
            : 1;

        $component = $section
            ->gradingComponents()
            ->create([
                'name' => $validated['name'],
                'weight_percentage' =>
                    $validated['weight_percentage'],
                'max_score' =>
                    $validated['max_score'],
                'period' => $period,
                'order' => $order,
                'is_locked' => false,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Component added successfully.',

            'data' => [
                'component' =>
                    $this->transformComponent($component),
            ],
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        string $componentId
    ): JsonResponse {
        $validated = $request->validate([
            'name' => 'required|string|max:100',

            'weight_percentage' =>
                'required|numeric|min:0.01|max:100',

            'max_score' =>
                'required|numeric|min:1|max:100000',

            'period' =>
                'nullable|in:midterm,finals',
        ]);

        $component = $this->resolveComponent($componentId);

        if ($component->is_locked) {
            return response()->json([
                'success' => false,
                'message' =>
                    'This grading component is locked and cannot be updated.',
            ], 422);
        }

        $period = $validated['period'] ?? null;

        $others = $component
            ->section
            ->gradingComponents()
            ->where('id', '!=', $component->id)
            ->where('period', $period)
            ->sum('weight_percentage');

        $newWeight = (float) $validated['weight_percentage'];

        if ($others + $newWeight > 100.01) {
            $group = $period
                ? ucfirst($period)
                : 'General';

            return response()->json([
                'success' => false,
                'message' =>
                    "{$group} weights cannot exceed 100%. Other components use: {$others}%.",
            ], 422);
        }

        $component->update([
            'name' => $validated['name'],
            'weight_percentage' =>
                $validated['weight_percentage'],
            'max_score' =>
                $validated['max_score'],
            'period' => $period,
        ]);

        return response()->json([
            'success' => true,
            'message' =>
                'Component updated successfully.',

            'data' => [
                'component' =>
                    $this->transformComponent(
                        $component->fresh()
                    ),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy
    |--------------------------------------------------------------------------
    */

    public function destroy(
        string $componentId
    ): JsonResponse {
        $component = $this->resolveComponent($componentId);

        if ($component->is_locked) {
            return response()->json([
                'success' => false,
                'message' =>
                    'This component is locked and cannot be deleted.',
            ], 422);
        }

        $component->delete();

        return response()->json([
            'success' => true,
            'message' =>
                'Component removed successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Toggle Lock
    |--------------------------------------------------------------------------
    */

    public function toggleLock(
        string $componentId
    ): JsonResponse {
        $component = $this->resolveComponent($componentId);

        $component->update([
            'is_locked' => ! $component->is_locked,
        ]);

        return response()->json([
            'success' => true,

            'message' => $component->is_locked
                ? 'Component locked successfully.'
                : 'Component unlocked successfully.',

            'data' => [
                'component' =>
                    $this->transformComponent(
                        $component->fresh()
                    ),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Section
    |--------------------------------------------------------------------------
    */

    private function resolveSection(
        string $encryptedId
    ): Section {
        $id = $this->decryptId($encryptedId);

        return Section::query()
            ->findOrFail($id);
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Component
    |--------------------------------------------------------------------------
    */

    private function resolveComponent(
        string $encryptedId
    ): GradingComponent {
        $id = $this->decryptId($encryptedId);

        return GradingComponent::query()
            ->findOrFail($id);
    }

    /*
    |--------------------------------------------------------------------------
    | Decrypt ID
    |--------------------------------------------------------------------------
    */

    private function decryptId(
        string $encryptedId
    ): int {
        try {
            return (int) Crypt::decryptString(
                $encryptedId
            );
        } catch (\Throwable $e) {
            abort(
                404,
                'Invalid resource identifier.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Encrypt ID
    |--------------------------------------------------------------------------
    */

    private function encryptId(
        int $id
    ): string {
        return Crypt::encryptString(
            (string) $id
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Transform Component
    |--------------------------------------------------------------------------
    */

    private function transformComponent(
        GradingComponent $component
    ): array {
        return [
            'id' => $this->encryptId(
                $component->id
            ),

            'section_id' => $this->encryptId(
                $component->section_id
            ),

            'name' => $component->name,

            'weight_percentage' =>
                $component->weight_percentage,

            'max_score' =>
                $component->max_score,

            'order' =>
                $component->order,

            'period' =>
                $component->period,

            'is_locked' =>
                (bool) $component->is_locked,
        ];
    }
}