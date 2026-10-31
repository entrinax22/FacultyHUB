<?php

use App\Models\AiSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function gradingBudgetPayload(array $overrides = []): array
{
    return array_merge([
        'grading_daily_request_budget' => 25,
        'grading_daily_input_token_budget' => 125000,
        'grading_requests_per_minute' => 2,
        'grading_auto_continue' => true,
    ], $overrides);
}

test('new AI settings use effective environment defaults and existing settings remain authoritative', function () {
    config()->set('ai_processing.grading_daily_request_budget', 37);
    config()->set('ai_processing.grading_daily_input_token_budget', 180000);
    config()->set('ai_processing.grading_requests_per_minute', 4);

    $settings = AiSetting::current();
    expect($settings->grading_daily_request_budget)->toBe(37)
        ->and($settings->grading_daily_input_token_budget)->toBe(180000)
        ->and($settings->grading_requests_per_minute)->toBe(4)
        ->and($settings->grading_auto_continue)->toBeTrue();

    config()->set('ai_processing.grading_daily_request_budget', 90);
    expect(AiSetting::current()->grading_daily_request_budget)->toBe(37);
});

test('administrator can update dynamic grading budget settings and read current usage', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    config()->set('auth.require_admin_2fa', false);
    $settings = AiSetting::current();
    DB::table('ai_processing_budget_windows')->insert([
        [
            'provider' => 'gemini', 'model' => 'model-a', 'category' => 'grading',
            'window_type' => 'daily', 'window_starts_at' => now('America/Los_Angeles')->startOfDay()->utc(),
            'request_count' => 3, 'estimated_input_tokens' => 4200, 'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'provider' => 'gemini', 'model' => 'model-b', 'category' => 'grading',
            'window_type' => 'minute', 'window_starts_at' => now('America/Los_Angeles')->startOfMinute()->utc(),
            'request_count' => 1, 'estimated_input_tokens' => 100, 'created_at' => now(), 'updated_at' => now(),
        ],
    ]);

    $this->actingAs($admin)->putJson('/admin/ai-settings/grading-budget', gradingBudgetPayload([
        'grading_daily_request_budget' => 40,
        'grading_daily_input_token_budget' => 200000,
        'grading_requests_per_minute' => 5,
        'grading_auto_continue' => false,
    ]))->assertOk()
        ->assertJsonPath('data.grading_budget.settings.grading_daily_request_budget', 40)
        ->assertJsonPath('data.grading_budget.usage.daily_requests_used', 3)
        ->assertJsonPath('data.grading_budget.usage.daily_requests_remaining', 37)
        ->assertJsonPath('data.grading_budget.usage.daily_tokens_used', 4200)
        ->assertJsonPath('data.grading_budget.usage.minute_requests_used', 1);

    expect($settings->fresh()->grading_auto_continue)->toBeFalse()
        ->and($settings->fresh()->grading_requests_per_minute)->toBe(5)
        ->and(DB::table('ai_processing_budget_windows')->sum('request_count'))->toBe(4);
});

test('only administrators can update grading budget settings', function () {
    config()->set('auth.require_admin_2fa', false);
    $faculty = User::factory()->create(['role' => 'faculty']);

    $this->actingAs($faculty)
        ->putJson('/admin/ai-settings/grading-budget', gradingBudgetPayload())
        ->assertForbidden();
});

test('grading budget settings reject nonpositive and out of range values', function () {
    config()->set('auth.require_admin_2fa', false);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->putJson('/admin/ai-settings/grading-budget', gradingBudgetPayload([
            'grading_daily_request_budget' => 0,
            'grading_daily_input_token_budget' => 10000001,
            'grading_requests_per_minute' => 121,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'grading_daily_request_budget',
            'grading_daily_input_token_budget',
            'grading_requests_per_minute',
        ]);
});
