<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('subjects/Index');
    }

    public function subjects_data(Request $request): JsonResponse
    {
        try {
            $query = Subject::withCount('sections');

            // Search filter
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Page controls
            $perPage = min(
                max($request->integer('per_page', 20), 1),
                100
            );

            $page = max(
                $request->integer('page', 1),
                1
            );

            $subjects = $query
                ->latest()
                ->paginate(
                    perPage: $perPage,
                    page: $page
                )
                ->withQueryString();

            $subjects->through(fn ($subject) => [
                'id' => Crypt::encryptString((string) $subject->id),
                'code' => $subject->code,
                'name' => $subject->name,
                'description' => $subject->description,
                'units' => $subject->units,
                'sections_count' => $subject->sections_count,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subjects data retrieved successfully.',

                'data' => $subjects->items(),

                'pagination' => [
                    'current_page' => $subjects->currentPage(),
                    'last_page' => $subjects->lastPage(),
                    'per_page' => $subjects->perPage(),
                    'total' => $subjects->total(),

                    'from' => $subjects->firstItem(),
                    'to' => $subjects->lastItem(),

                    'has_more_pages' => $subjects->hasMorePages(),

                    'next_page_url' => $subjects->nextPageUrl(),
                    'previous_page_url' => $subjects->previousPageUrl(),

                    'first_page_url' => $subjects->url(1),
                    'last_page_url' => $subjects->url(
                        $subjects->lastPage()
                    ),
                ],

                'filters' => [
                    'search' => $request->get('search'),
                ],
            ], 200);

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve subjects data.',
                'data' => [],
                'pagination' => null,
            ], 500);
        }
    }

    public function create(): Response
    {
        return Inertia::render('subjects/Form');
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string|max:20|unique:subjects,code',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'units' => 'required|numeric|min:0.5|max:10',
            ]);

            $subject = Subject::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Subject stored successfully.',
                'subject' => [
                    'id' => Crypt::encryptString((string) $subject->id),
                    'code' => $subject->code,
                    'name' => $subject->name,
                    'description' => $subject->description,
                    'units' => $subject->units,
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to store subject data.',
            ], 500);
        }
    }

    public function edit(string $id): Response
    {
        try {
            $subjectId = Crypt::decryptString($id);

            $subject = Subject::findOrFail($subjectId);

            return Inertia::render('subjects/Form', [
                'subject' => [
                    'id' => Crypt::encryptString((string) $subject->id),
                    'code' => $subject->code,
                    'name' => $subject->name,
                    'description' => $subject->description,
                    'units' => $subject->units,
                ],
            ]);
        } catch (DecryptException $e) {
            abort(404);
        } catch (\Throwable $e) {
            report($e);

            abort(500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $subjectId = Crypt::decryptString($id);

            $subject = Subject::findOrFail($subjectId);

            $validated = $request->validate([
                'code' => 'required|string|max:20|unique:subjects,code,' . $subject->id,
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'units' => 'required|numeric|min:0.5|max:10',
            ]);

            $subject->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Subject updated successfully.',
                'subject' => [
                    'id' => Crypt::encryptString((string) $subject->id),
                    'code' => $subject->code,
                    'name' => $subject->name,
                    'description' => $subject->description,
                    'units' => $subject->units,
                ],
            ]);
        } catch (DecryptException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid subject ID.',
            ], 400);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update subject data.',
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $subjectId = Crypt::decryptString($id);

            $subject = Subject::findOrFail($subjectId);

            $subject->delete();

            return response()->json([
                'success' => true,
                'message' => 'Subject deleted successfully.',
            ]);
        } catch (DecryptException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid subject ID.',
            ], 400);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete subject data.',
            ], 500);
        }
    }
}
