<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SemesterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    */

    public function index(): Response
    {
        return Inertia::render('semesters/Index');
    }

    public function create(): Response
    {
        return Inertia::render('semesters/Form');
    }

    public function edit(string $id): Response
    {
        try {
            $semesterId = Crypt::decryptString($id);

            $semester = Semester::findOrFail($semesterId);

            return Inertia::render('semesters/Form', [
                'semester' => [
                    'id' => Crypt::encryptString((string) $semester->id),
                    'name' => $semester->name,
                    'school_year' => $semester->school_year,
                    'start_date' => $semester->start_date,
                    'end_date' => $semester->end_date,
                    'is_active' => $semester->is_active,
                ],
            ]);
        } catch (DecryptException $e) {
            abort(404);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    */

    public function semesters_data(Request $request): JsonResponse
    {
        try {
            $query = Semester::query();

            /*
            |--------------------------------------------------------------------------
            | Search
            |--------------------------------------------------------------------------
            */

            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('school_year', 'like', "%{$search}%");
                });
            }

            /*
            |--------------------------------------------------------------------------
            | Pagination
            |--------------------------------------------------------------------------
            */

            $perPage = min(
                max($request->integer('per_page', 20), 1),
                100
            );

            $page = max(
                $request->integer('page', 1),
                1
            );

            $semesters = $query
                ->latest()
                ->paginate(
                    perPage: $perPage,
                    page: $page
                )
                ->withQueryString();

            /*
            |--------------------------------------------------------------------------
            | Transform Data
            |--------------------------------------------------------------------------
            */

            $semesters->through(fn ($semester) => [
                'id' => Crypt::encryptString((string) $semester->id),
                'name' => $semester->name,
                'school_year' => $semester->school_year,
                'start_date' => $semester->start_date,
                'end_date' => $semester->end_date,
                'is_active' => (bool) $semester->is_active,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Semesters retrieved successfully.',
                'data' => $semesters->items(),
                'pagination' => [
                    'current_page' => $semesters->currentPage(),
                    'last_page' => $semesters->lastPage(),
                    'per_page' => $semesters->perPage(),
                    'total' => $semesters->total(),
                    'from' => $semesters->firstItem(),
                    'to' => $semesters->lastItem(),
                    'has_more_pages' => $semesters->hasMorePages(),
                    'next_page_url' => $semesters->nextPageUrl(),
                    'previous_page_url' => $semesters->previousPageUrl(),
                    'first_page_url' => $semesters->url(1),
                    'last_page_url' => $semesters->url($semesters->lastPage()),
                ],
                'filters' => [
                    'search' => $request->get('search'),
                ],
            ], 200);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve semesters.',
                'data' => [],
                'pagination' => null,
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    Rule::in([
                        '1st Semester',
                        '2nd Semester',
                        'Summer',
                    ]),
                ],
                'school_year' => [
                    'required',
                    'string',
                    'max:20',
                ],
                'start_date' => [
                    'required',
                    'date',
                ],
                'end_date' => [
                    'required',
                    'date',
                    'after:start_date',
                ],
            ]);

            $semester = Semester::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Semester created successfully.',
                'data' => [
                    'id' => Crypt::encryptString((string) $semester->id),
                    'name' => $semester->name,
                    'school_year' => $semester->school_year,
                    'start_date' => $semester->start_date,
                    'end_date' => $semester->end_date,
                    'is_active' => (bool) $semester->is_active,
                ],
            ], 201);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create semester.',
            ], 500);
        }
    }

    public function update(
        Request $request,
        string $id
    ): JsonResponse {
        try {
            $semesterId = Crypt::decryptString($id);

            $semester = Semester::findOrFail($semesterId);

            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    Rule::in([
                        '1st Semester',
                        '2nd Semester',
                        'Summer',
                    ]),
                ],
                'school_year' => [
                    'required',
                    'string',
                    'max:20',
                ],
                'start_date' => [
                    'required',
                    'date',
                ],
                'end_date' => [
                    'required',
                    'date',
                    'after:start_date',
                ],
            ]);

            $semester->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Semester updated successfully.',
                'data' => [
                    'id' => Crypt::encryptString((string) $semester->id),
                    'name' => $semester->name,
                    'school_year' => $semester->school_year,
                    'start_date' => $semester->start_date,
                    'end_date' => $semester->end_date,
                    'is_active' => (bool) $semester->is_active,
                ],
            ], 200);
        } catch (DecryptException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid semester ID.',
            ], 404);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update semester.',
            ], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $semesterId = Crypt::decryptString($id);

            $semester = Semester::findOrFail($semesterId);

            $semester->delete();

            return response()->json([
                'success' => true,
                'message' => 'Semester deleted successfully.',
            ], 200);
        } catch (DecryptException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid semester ID.',
            ], 404);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete semester.',
            ], 500);
        }
    }

    public function setActive(string $id): JsonResponse
    {
        try {
            $semesterId = Crypt::decryptString($id);

            $semester = Semester::findOrFail($semesterId);

            Semester::where('is_active', true)
                ->update([
                    'is_active' => false,
                ]);

            $semester->update([
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => "{$semester->name} is now the active semester.",
                'data' => [
                    'id' => Crypt::encryptString((string) $semester->id),
                    'name' => $semester->name,
                    'school_year' => $semester->school_year,
                    'start_date' => $semester->start_date,
                    'end_date' => $semester->end_date,
                    'is_active' => true,
                ],
            ], 200);
        } catch (DecryptException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid semester ID.',
            ], 404);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to set active semester.',
            ], 500);
        }
    }
}
