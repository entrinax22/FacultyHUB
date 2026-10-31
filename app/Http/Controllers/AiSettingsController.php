<?php

namespace App\Http\Controllers;

use App\Models\AiProcessingBudgetWindow;
use App\Models\AiProviderConfig;
use App\Models\AiSetting;
use App\Services\AiProcessingRunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class AiSettingsController extends Controller
{
    private const MAX_GRADING_DAILY_REQUESTS = 10000;

    private const MAX_GRADING_DAILY_TOKENS = 10000000;

    private const MAX_GRADING_REQUESTS_PER_MINUTE = 120;

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
                    'grading_budget' => $this->gradingBudgetPayload($settings),
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

    public function updateGradingBudget(Request $request, AiProcessingRunService $processing): JsonResponse
    {
        $validated = $request->validate([
            'grading_daily_request_budget' => ['required', 'integer', 'between:1,'.self::MAX_GRADING_DAILY_REQUESTS],
            'grading_daily_input_token_budget' => ['required', 'integer', 'between:1,'.self::MAX_GRADING_DAILY_TOKENS],
            'grading_requests_per_minute' => ['required', 'integer', 'between:1,'.self::MAX_GRADING_REQUESTS_PER_MINUTE],
            'grading_auto_continue' => ['required', 'boolean'],
        ]);

        try {
            $settings = DB::transaction(function () use ($validated) {
                $settings = AiSetting::query()->lockForUpdate()->first() ?? AiSetting::current();
                $settings->update($validated);

                return $settings->fresh();
            });

            if ($settings->grading_auto_continue) {
                $processing->reevaluatePausedGradingRuns();
            }

            return response()->json([
                'success' => true,
                'message' => 'AI grading budget settings updated successfully.',
                'data' => ['grading_budget' => $this->gradingBudgetPayload($settings)],
            ], 200);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update AI grading budget settings.',
                'data' => null,
            ], 500);
        }
    }

    private function gradingBudgetPayload(AiSetting $settings): array
    {
        $timezone = (string) config('ai_processing.grading_budget_timezone', 'America/Los_Angeles');
        $now = now($timezone);
        $dailyStart = $now->copy()->startOfDay()->utc();
        $minuteStart = $now->copy()->startOfMinute()->utc();
        $scope = AiProcessingBudgetWindow::query()
            ->where('provider', 'gemini')
            ->where('category', 'grading');
        $dailyUsage = (clone $scope)->where('window_type', 'daily')
            ->where('window_starts_at', $dailyStart)
            ->selectRaw('COALESCE(SUM(request_count), 0) as request_count, COALESCE(SUM(estimated_input_tokens), 0) as estimated_input_tokens')
            ->first();
        $minuteUsage = (clone $scope)->where('window_type', 'minute')
            ->where('window_starts_at', $minuteStart)
            ->sum('request_count');
        $dailyRequests = (int) $dailyUsage->request_count;
        $dailyTokens = (int) $dailyUsage->estimated_input_tokens;
        $minuteRequests = (int) $minuteUsage;

        return [
            'settings' => [
                'grading_daily_request_budget' => $settings->grading_daily_request_budget,
                'grading_daily_input_token_budget' => $settings->grading_daily_input_token_budget,
                'grading_requests_per_minute' => $settings->grading_requests_per_minute,
                'grading_auto_continue' => $settings->grading_auto_continue,
            ],
            'usage' => [
                'daily_requests_used' => $dailyRequests,
                'daily_requests_remaining' => max(0, $settings->grading_daily_request_budget - $dailyRequests),
                'daily_tokens_used' => $dailyTokens,
                'daily_tokens_remaining' => max(0, $settings->grading_daily_input_token_budget - $dailyTokens),
                'minute_requests_used' => $minuteRequests,
                'minute_requests_remaining' => max(0, $settings->grading_requests_per_minute - $minuteRequests),
                'daily_reset_at' => $now->copy()->addDay()->startOfDay()->utc()->toIso8601String(),
                'minute_reset_at' => $now->copy()->addMinute()->startOfMinute()->utc()->toIso8601String(),
            ],
            'bounds' => [
                'grading_daily_request_budget' => ['min' => 1, 'max' => self::MAX_GRADING_DAILY_REQUESTS],
                'grading_daily_input_token_budget' => ['min' => 1, 'max' => self::MAX_GRADING_DAILY_TOKENS],
                'grading_requests_per_minute' => ['min' => 1, 'max' => self::MAX_GRADING_REQUESTS_PER_MINUTE],
            ],
        ];
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
