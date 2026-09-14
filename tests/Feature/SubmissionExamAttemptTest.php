<?php

use App\Models\Submission;
use Carbon\Carbon;

it('recognizes a started pending attempt as active even when submitted_at has a database default', function () {
    $submission = new Submission([
        'status' => 'pending',
        'started_at' => now(),
        'expires_at' => now()->addMinutes(10),
    ]);
    $submission->submitted_at = Carbon::now();

    expect($submission->isActiveExamAttempt())->toBeTrue();
});

it('does not recognize an approved or expired attempt as active', function () {
    $approved = new Submission([
        'status' => 'approved',
        'started_at' => now(),
        'expires_at' => now()->addMinutes(10),
    ]);
    $expired = new Submission([
        'status' => 'pending',
        'started_at' => now()->subMinutes(20),
        'expires_at' => now()->subMinute(),
    ]);

    expect($approved->isActiveExamAttempt())->toBeFalse()
        ->and($expired->isActiveExamAttempt())->toBeFalse();
});