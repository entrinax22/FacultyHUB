<?php

namespace App\Http\Controllers;

use App\Models\AiSetting;
use App\Models\AiProviderConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class AiSettingsController extends Controller
{
    /**
     * Inertia page.
     */
    public function edit(): Response
    {
        return Inertia::render('admin/AiSettings');
    }

    /**
     * Get AI settings and providers.
     */
    public function data(): JsonResponse
    {
        try {
            $settings = AiSetting::current();

            AiProviderConfig::ensureDefaults();

            $providers = AiProviderConfig::query()
                ->orderBy('id')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'AI settings retrieved successfully.',
                'data' => [
                    'settings' => [
                        'enabled' => $settings->enabled,
                        'temperature' => (float) $settings->temperature,
                        'max_output_tokens' => $settings->max_output_tokens,
                        'include_assignment_instructions' =>
                            $settings->include_assignment_instructions,
                        'include_rubric' =>
                            $settings->include_rubric,
                        'context_enabled' =>
                            $settings->context_enabled,
                        'global_context' =>
                            $settings->global_context ?? '',
                        'essay_context' =>
                            $settings->essay_context ?? '',
                        'code_context' =>
                            $settings->code_context ?? '',
                        'plagiarism_context' =>
                            $settings->plagiarism_context ?? '',
                    ],

                    'providers' => $providers
                        ->map(fn (AiProviderConfig $provider) =>
                            $this->formatProvider($provider)
                        )
                        ->values(),
                ],
            ], 200);

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve AI settings.',
                'data' => [
                    'settings' => null,
                    'providers' => [],
                ],
            ], 500);
        }
    }

    /**
     * Update general AI settings.
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'enabled' => ['required', 'boolean'],
                'temperature' => [
                    'required',
                    'numeric',
                    'between:0,1',
                ],
                'max_output_tokens' => [
                    'required',
                    'integer',
                    'between:256,8192',
                ],
                'include_assignment_instructions' => [
                    'required',
                    'boolean',
                ],
                'include_rubric' => [
                    'required',
                    'boolean',
                ],
                'context_enabled' => [
                    'required',
                    'boolean',
                ],
                'global_context' => [
                    'nullable',
                    'string',
                    'max:6000',
                ],
                'essay_context' => [
                    'nullable',
                    'string',
                    'max:6000',
                ],
                'code_context' => [
                    'nullable',
                    'string',
                    'max:6000',
                ],
                'plagiarism_context' => [
                    'nullable',
                    'string',
                    'max:6000',
                ],
            ]);

            $settings = AiSetting::current();

            $settings->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'AI settings updated successfully.',
                'data' => [
                    'settings' => [
                        'enabled' => $settings->enabled,
                        'temperature' => (float) $settings->temperature,
                        'max_output_tokens' =>
                            $settings->max_output_tokens,
                        'include_assignment_instructions' =>
                            $settings->include_assignment_instructions,
                        'include_rubric' =>
                            $settings->include_rubric,
                        'context_enabled' =>
                            $settings->context_enabled,
                        'global_context' =>
                            $settings->global_context ?? '',
                        'essay_context' =>
                            $settings->essay_context ?? '',
                        'code_context' =>
                            $settings->code_context ?? '',
                        'plagiarism_context' =>
                            $settings->plagiarism_context ?? '',
                    ],
                ],
            ], 200);

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update AI settings.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Update an AI provider.
     */
    public function updateProvider(
        Request $request,
        string $id
    ): JsonResponse {
        try {
            $provider = $this->findProvider($id);

            if (! $provider) {
                return response()->json([
                    'success' => false,
                    'message' => 'AI provider not found.',
                    'data' => null,
                ], 404);
            }

            $validated = $request->validate([
                'api_key' => [
                    'nullable',
                    'string',
                    'max:500',
                ],
                'clear_api_key' => [
                    'required',
                    'boolean',
                ],
                'models' => [
                    'required',
                    'string',
                    'max:2000',
                ],
                'selected_model' => [
                    'required',
                    'string',
                    'max:100',
                ],
            ]);

            $models = collect(
                preg_split(
                    '/[\r\n,]+/',
                    $validated['models']
                )
            )
                ->map(fn (string $model) => trim($model))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (! in_array(
                $validated['selected_model'],
                $models,
                true
            )) {
                $models[] = $validated['selected_model'];
            }

            $provider->fill([
                'models' => $models,
                'selected_model' =>
                    $validated['selected_model'],
            ]);

            if ($validated['clear_api_key']) {
                $provider->api_key = null;

                $provider->status =
                    AiProviderConfig::STATUS_INACTIVE;

                $provider->status_message =
                    'No backend API key saved.';

                $provider->quota_exhausted_at = null;

            } elseif (filled($validated['api_key'])) {
                $provider->api_key =
                    trim($validated['api_key']);

                $provider->status =
                    AiProviderConfig::STATUS_INACTIVE;

                $provider->status_message =
                    'API key updated. Run a status check.';

                $provider->quota_exhausted_at = null;
            }

            $provider->save();

            return response()->json([
                'success' => true,
                'message' => "{$provider->name} settings updated successfully.",
                'data' => [
                    'provider' =>
                        $this->formatProvider($provider),
                ],
            ], 200);

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update AI provider.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Enable or disable an AI provider.
     */
    public function toggleProvider(
        string $id
    ): JsonResponse {
        try {
            $provider = $this->findProvider($id);

            if (! $provider) {
                return response()->json([
                    'success' => false,
                    'message' => 'AI provider not found.',
                    'data' => null,
                ], 404);
            }

            if ($provider->enabled) {
                $provider->update([
                    'enabled' => false,
                    'status' =>
                        AiProviderConfig::STATUS_INACTIVE,
                    'status_message' =>
                        'Provider disabled.',
                ]);

                $message =
                    "{$provider->name} disabled.";

            } else {
                AiProviderConfig::query()
                    ->whereKeyNot($provider->id)
                    ->update([
                        'enabled' => false,
                    ]);

                $provider->update([
                    'enabled' => true,
                    'status_message' =>
                        'Provider enabled. Run a status check.',
                ]);

                $message =
                    "{$provider->name} enabled.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'provider' =>
                        $this->formatProvider($provider),
                ],
            ], 200);

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update provider status.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Check an AI provider connection.
     */
    public function checkProvider(
        string $id
    ): JsonResponse {
        try {
            $provider = $this->findProvider($id);

            if (! $provider) {
                return response()->json([
                    'success' => false,
                    'message' => 'AI provider not found.',
                    'data' => null,
                ], 404);
            }

            $status = $this->checkProviderStatus($provider);

            $provider->update($status);

            $provider->refresh();

            return response()->json([
                'success' => true,
                'message' => $provider->status_message,
                'data' => [
                    'provider' =>
                        $this->formatProvider($provider),
                ],
            ], 200);

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check AI provider.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Find provider using encrypted ID.
     */
    private function findProvider(
        string $id
    ): ?AiProviderConfig {
        try {
            $providerId = Crypt::decryptString($id);
        } catch (\Throwable $e) {
            return null;
        }

        return AiProviderConfig::find($providerId);
    }

    /**
     * Format provider for JSON responses.
     *
     * Never return the actual API key.
     */
    private function formatProvider(
        AiProviderConfig $provider
    ): array {
        return [
            'id' => Crypt::encryptString(
                (string) $provider->id
            ),

            'name' => $provider->name,

            'provider' => $provider->provider,

            'models' => $provider->models ?? [],

            'selected_model' =>
                $provider->selected_model,

            'enabled' => $provider->enabled,

            'status' => $provider->status,

            'status_message' =>
                $provider->status_message,

            'status_checked_at' =>
                $provider->status_checked_at
                    ?->toDateTimeString(),

            'quota_exhausted_at' =>
                $provider->quota_exhausted_at
                    ?->toDateTimeString(),

            'has_api_key' =>
                $provider->hasStoredOrEnvKey(),

            'connected' =>
                $provider->provider === 'gemini',
        ];
    }

    /**
     * Check provider connection status.
     */
    private function checkProviderStatus(
        AiProviderConfig $provider
    ): array {
        if (! $provider->enabled) {
            return [
                'status' =>
                    AiProviderConfig::STATUS_INACTIVE,

                'status_message' =>
                    'Provider is disabled.',

                'status_checked_at' =>
                    now(),
            ];
        }

        $apiKey = $provider->resolvedApiKey();

        if (blank($apiKey)) {
            return [
                'status' =>
                    AiProviderConfig::STATUS_INACTIVE,

                'status_message' =>
                    'No backend API key saved.',

                'status_checked_at' =>
                    now(),
            ];
        }

        if ($provider->provider !== 'gemini') {
            return [
                'status' =>
                    AiProviderConfig::STATUS_INACTIVE,

                'status_message' =>
                    'Credentials are saved, but this provider is not connected to assessment calls yet.',

                'status_checked_at' =>
                    now(),
            ];
        }

        try {
            $response = Http::timeout(15)->get(
                "https://generativelanguage.googleapis.com/v1beta/models/{$provider->selected_model}",
                [
                    'key' => $apiKey,
                ]
            );

            if ($response->successful()) {
                return [
                    'status' =>
                        AiProviderConfig::STATUS_ACTIVE,

                    'status_message' =>
                        'API key and selected model are active.',

                    'status_checked_at' =>
                        now(),

                    'quota_exhausted_at' =>
                        null,
                ];
            }

            if ($response->status() === 429) {
                return [
                    'status' =>
                        AiProviderConfig::STATUS_EXHAUSTED,

                    'status_message' =>
                        'Provider quota or rate limit is exhausted.',

                    'status_checked_at' =>
                        now(),

                    'quota_exhausted_at' =>
                        now(),
                ];
            }

            return [
                'status' =>
                    AiProviderConfig::STATUS_INACTIVE,

                'status_message' =>
                    "Provider returned HTTP {$response->status()}.",

                'status_checked_at' =>
                    now(),
            ];

        } catch (\Throwable $e) {
            report($e);

            return [
                'status' =>
                    AiProviderConfig::STATUS_INACTIVE,

                'status_message' =>
                    'Unable to connect to the provider.',

                'status_checked_at' =>
                    now(),
            ];
        }
    }
}