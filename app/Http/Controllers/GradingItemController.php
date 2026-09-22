<?php

namespace App\Http\Controllers;

use App\Models\GradingComponent;
use App\Models\GradingItem;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;

class GradingItemController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Index Page
    |--------------------------------------------------------------------------
    */

    public function index(string $sectionId): Response
    {
        $section = $this->resolveSection($sectionId);

        return Inertia::render('class-record/Items', [
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
        try {
            $sectionId = Crypt::decryptString($sectionId);

            $section = Section::with([
                'subject:id,code,name',
                'semester:id,name',
            ])->findOrFail($sectionId);

            $components = GradingComponent::where(
                'section_id',
                $sectionId
            )
                ->orderBy('order')
                ->get();

            /*
            |--------------------------------------------------------------------------
            | Create ONE encrypted ID for every component
            |--------------------------------------------------------------------------
            |
            | Important:
            | Crypt::encryptString() generates a different encrypted value
            | every time it is called.
            |
            | Therefore, we create the encrypted component ID once and reuse
            | it when returning the items.
            |
            */

            $componentIds = [];

            foreach ($components as $component) {
                $componentIds[$component->id] = Crypt::encryptString(
                    (string) $component->id
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Items
            |--------------------------------------------------------------------------
            */

            $items = GradingItem::where(
                'section_id',
                $sectionId
            )
                ->orderBy('component_id')
                ->orderBy('order')
                ->get();

            /*
            |--------------------------------------------------------------------------
            | Transform Components
            |--------------------------------------------------------------------------
            */

            $componentData = $components->map(function ($component) use (
                $componentIds
            ) {
                return [
                    'id' => $componentIds[$component->id],
                    'name' => $component->name,
                    'weight_percentage' => $component->weight_percentage,
                    'period' => $component->period,
                    'order' => $component->order,
                    'is_locked' => $component->is_locked,
                ];
            })->values();

            /*
            |--------------------------------------------------------------------------
            | Transform Items
            |--------------------------------------------------------------------------
            */

            $itemData = $items->map(function ($item) use (
                $componentIds
            ) {
                return [
                    'id' => Crypt::encryptString(
                        (string) $item->id
                    ),

                    'section_id' => Crypt::encryptString(
                        (string) $item->section_id
                    ),

                    /*
                    | IMPORTANT:
                    | Use the SAME encrypted component ID that was
                    | already generated above.
                    */
                    'component_id' => $componentIds[$item->component_id]
                        ?? null,

                    'assignment_id' => $item->assignment_id
                        ? Crypt::encryptString(
                            (string) $item->assignment_id
                        )
                        : null,

                    'name' => $item->name,
                    'max_score' => $item->max_score,
                    'order' => $item->order,
                    'is_enabled' => $item->is_enabled,
                ];
            })->values();

            /*
            |--------------------------------------------------------------------------
            | Section
            |--------------------------------------------------------------------------
            */

            $sectionData = [
                'id' => Crypt::encryptString(
                    (string) $section->id
                ),

                'name' => $section->name,

                'subject' => [
                    'id' => Crypt::encryptString(
                        (string) $section->subject->id
                    ),
                    'code' => $section->subject->code,
                    'name' => $section->subject->name,
                ],

                'semester' => [
                    'id' => Crypt::encryptString(
                        (string) $section->semester->id
                    ),
                    'name' => $section->semester->name,
                ],
            ];

            return response()->json([
                'success' => true,
                'message' => 'Grading items loaded successfully.',
                'data' => [
                    'section' => $sectionData,
                    'components' => $componentData,
                    'items' => $itemData,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load grading items.',
                'error' => $e->getMessage(),
            ], 500);
        }
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
            'component_id' =>
                'required|string',

            'name' =>
                'required|string|max:100',

            'max_score' =>
                'required|numeric|min:1|max:100000',
        ]);

        $section = $this->resolveSection(
            $sectionId
        );

        $componentId = $this->decryptId(
            $validated['component_id']
        );

        $component = GradingComponent::query()
            ->where('section_id', $section->id)
            ->findOrFail($componentId);

        if ($component->is_locked) {
            return response()->json([
                'success' => false,
                'message' =>
                    'This grading component is locked.',
            ], 422);
        }

        $order = GradingItem::query()
            ->where('section_id', $section->id)
            ->where(
                'component_id',
                $component->id
            )
            ->max('order');

        $order = $order !== null
            ? $order + 1
            : 1;

        $item = GradingItem::query()->create([
            'section_id' =>
                $section->id,

            'component_id' =>
                $component->id,

            'name' =>
                $validated['name'],

            'max_score' =>
                $validated['max_score'],

            'order' =>
                $order,

            'is_enabled' =>
                true,
        ]);

        return response()->json([
            'success' => true,
            'message' =>
                'Item added successfully.',

            'data' => [
                'item' =>
                    $this->transformItem($item),
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
        string $itemId
    ): JsonResponse {
        $validated = $request->validate([
            'name' =>
                'required|string|max:100',

            'max_score' =>
                'required|numeric|min:1|max:100000',
        ]);

        $item = $this->resolveItem(
            $itemId
        );

        $component = GradingComponent::query()
            ->where(
                'section_id',
                $item->section_id
            )
            ->find($item->component_id);

        if ($component?->is_locked) {
            return response()->json([
                'success' => false,
                'message' =>
                    'This grading component is locked.',
            ], 422);
        }

        $item->update([
            'name' =>
                $validated['name'],

            'max_score' =>
                $validated['max_score'],
        ]);

        return response()->json([
            'success' => true,
            'message' =>
                'Item updated successfully.',

            'data' => [
                'item' =>
                    $this->transformItem(
                        $item->fresh()
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
        string $itemId
    ): JsonResponse {
        $item = $this->resolveItem(
            $itemId
        );

        $component = GradingComponent::query()
            ->where(
                'section_id',
                $item->section_id
            )
            ->find($item->component_id);

        if ($component?->is_locked) {
            return response()->json([
                'success' => false,
                'message' =>
                    'This grading component is locked.',
            ], 422);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' =>
                'Item removed successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Toggle
    |--------------------------------------------------------------------------
    */

    public function toggle(
        string $itemId
    ): JsonResponse {
        $item = $this->resolveItem(
            $itemId
        );

        $component = GradingComponent::query()
            ->where(
                'section_id',
                $item->section_id
            )
            ->find($item->component_id);

        if ($component?->is_locked) {
            return response()->json([
                'success' => false,
                'message' =>
                    'This grading component is locked.',
            ], 422);
        }

        $item->update([
            'is_enabled' =>
                ! $item->is_enabled,
        ]);

        return response()->json([
            'success' => true,

            'message' => $item->is_enabled
                ? 'Item enabled successfully.'
                : 'Item disabled successfully.',

            'data' => [
                'item' =>
                    $this->transformItem(
                        $item->fresh()
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
        $id = $this->decryptId(
            $encryptedId
        );

        return Section::query()
            ->findOrFail($id);
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Item
    |--------------------------------------------------------------------------
    */

    private function resolveItem(
        string $encryptedId
    ): GradingItem {
        $id = $this->decryptId(
            $encryptedId
        );

        return GradingItem::query()
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
    | Transform Item
    |--------------------------------------------------------------------------
    */

    private function transformItem(
        GradingItem $item
    ): array {
        return [
            'id' => $this->encryptId(
                $item->id
            ),

            'section_id' => $this->encryptId(
                $item->section_id
            ),

            'component_id' =>
                $item->component_id
                    ? $this->encryptId(
                        $item->component_id
                    )
                    : null,

            'assignment_id' =>
                $item->assignment_id
                    ? $this->encryptId(
                        $item->assignment_id
                    )
                    : null,

            'name' =>
                $item->name,

            'max_score' =>
                $item->max_score,

            'order' =>
                $item->order,

            'is_enabled' =>
                (bool) $item->is_enabled,
        ];
    }
}
