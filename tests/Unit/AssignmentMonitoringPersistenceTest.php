<?php

use App\Models\Assignment;

it('persists the monitoring flag as a boolean', function () {
    $assignment = new Assignment([
        'section_id' => 1,
        'title' => 'Monitoring persistence test',
        'instructions' => 'Test instructions',
        'type' => 'mcq',
        'max_score' => 10,
        'proctoring_enabled' => true,
    ]);

    expect($assignment->proctoring_enabled)->toBeTrue();

    $assignment->proctoring_enabled = false;

    expect($assignment->proctoring_enabled)->toBeFalse();
});