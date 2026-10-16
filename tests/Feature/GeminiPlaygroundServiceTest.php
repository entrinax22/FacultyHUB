<?php

use App\Services\AIGraderService;
use Illuminate\Support\Facades\Http;

test('playground returns plain text and sanitized provider failures', function () {
    config()->set('gemini_playground.enabled', true);
    config()->set('gemini_playground.production_enabled', false);
    config()->set('services.gemini.key', 'test-key');
    $successBody = ['candidates' => [['content' => ['parts' => [['text' => 'Gemini connection successful']]]]]];
    Http::fake(['*' => Http::sequence()
        ->push($successBody, 200)
        ->push(['error' => ['message' => 'secret should not leak']], 503)]);

    $success = app(AIGraderService::class)->testPlainTextPrompt('Reply with exactly: Gemini connection successful');

    expect($success['success'])->toBeTrue()
        ->and($success['response_text'])->toBe('Gemini connection successful')
        ->and($success['key_source'])->toBe('environment');

    $failure = app(AIGraderService::class)->testPlainTextPrompt('test');

    expect($failure['success'])->toBeFalse()
        ->and($failure['http_status'])->toBe(503)
        ->and($failure['error_code'])->toBe('provider_unavailable')
        ->and($failure['error_message'])->not->toContain('secret');
});

test('playground remains disabled by default and requires a production opt-in', function () {
    config()->set('gemini_playground.enabled', false);
    Http::fake();
    $disabled = app(AIGraderService::class)->testPlainTextPrompt('test');

    expect($disabled['error_code'])->toBe('playground_disabled');
    Http::assertNothingSent();

    config()->set('gemini_playground.enabled', true);
    config()->set('gemini_playground.production_enabled', false);
    config()->set('app.env', 'production');
    $production = app(AIGraderService::class)->testPlainTextPrompt('test');

    expect($production['error_code'])->toBe('playground_disabled');
    Http::assertNothingSent();
});

test('interactions diagnostic parses model output and sanitizes failures', function () {
    config()->set('gemini_playground.enabled', true); config()->set('services.gemini.key', 'test-key');
    Http::fake(['*' => Http::sequence()->push(['steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'Gemini connection successful']]]]], 200)->push(['error' => ['message' => 'secret']], 429)]);
    $success = app(AIGraderService::class)->testInteractionsPrompt('test');
    $failure = app(AIGraderService::class)->testInteractionsPrompt('test');
    expect($success['response_text'])->toBe('Gemini connection successful')->and($failure['error_code'])->toBe('provider_rate_limited')->and($failure['error_message'])->not->toContain('secret');
});
